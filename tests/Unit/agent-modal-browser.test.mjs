import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { chromium } from 'playwright';

const script = await readFile(
    new URL('../../public/js/agent-modal.js', import.meta.url),
    'utf8',
);
const modalTemplate = await readFile(
    new URL(
        '../../resources/views/ai-plus/agent-workspace/agents/_agent-form-modal.blade.php',
        import.meta.url,
    ),
    'utf8',
);
const modalStyles = [...modalTemplate.matchAll(/<style>([\s\S]*?)<\/style>/g)]
    .map((match) => match[1])
    .join('\n');
const fixture = `<!doctype html><html><head><meta name="csrf-token" content="test"><style>.form-group{display:flex;flex-direction:column}${modalStyles}</style></head><body>
<button id="create-agent-btn">Create</button><div id="agents-grid"><button class="edit-agent" data-agent-id="10">Edit</button></div>
<div id="agent-modal" style="display:none"><h3 id="modal-title"></h3><button id="modal-close">Close</button>
<form id="agent-form"><input id="form-method" name="_method"><input id="agent-id" name="agent_id">
<input id="title" name="title" required><textarea id="description"></textarea><textarea id="system_prompt"></textarea>
<fieldset id="agent-connections-field" class="form-group agent-connections-field" hidden><input id="agent-connections-search" type="search"><div id="agent-connections-list"></div><div id="agent-connections-status"></div></fieldset>
<button type="button" id="modal-cancel">Cancel</button><button type="submit" id="modal-submit">Save</button></form></div></body></html>`;

async function setup(
    t,
    { optionsFail = false, enabled = true, saved = [], agents = null } = {},
) {
    const browser = await chromium.launch({ headless: true });
    t.after(() => browser.close());
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    t.after(() => assert.deepEqual(errors, []));
    await page.route('http://agent.test/**', async (route) => {
        const request = route.request();
        const url = new URL(request.url());

        if (url.pathname.endsWith('connection-options')) {
            await route.fulfill({
                status: optionsFail ? 503 : 200,
                json: {
                    enabled,
                    agents: agents ?? [
                        { id: 10, title: 'Coordinator' },
                        { id: 20, title: 'Reviewer' },
                        { id: 30, title: 'Writer <script>bad()</script>' },
                    ],
                },
            });
        } else if (url.pathname.endsWith('/10') && request.method() === 'GET') {
            await route.fulfill({
                json: { id: 10, title: 'Coordinator', connections: saved },
            });
        } else if (request.method() === 'POST') {
            await route.fulfill({
                status: 422,
                json: {
                    message: 'Test validation response keeps this form open.',
                },
            });
        } else {
            await route.fulfill({ contentType: 'text/html', body: fixture });
        }
    });
    await page.goto('http://agent.test/');
    await page.evaluate(() => {
        window.AgentModalConfig = {
            connectionOptionsUrl:
                '/ai-plus/agent-workspace/agents/connection-options',
            maxConnections: 10,
        };
    });
    await page.addScriptTag({ content: script });
    await page.evaluate(() =>
        document.dispatchEvent(new Event('DOMContentLoaded')),
    );

    return page;
}

test('selects Agents, keeps roles during search, and submits multipart edit using POST', async (t) => {
    const page = await setup(t, {
        saved: [{ agent_id: 20, role: 'Review objectives' }],
    });
    await page.locator('.edit-agent').click();
    await page.getByText('1 / 10 Agents connected').waitFor();
    assert.equal(await page.locator('.agent-connection-row').count(), 2);
    assert.equal(
        await page.locator('.agent-connection-role').inputValue(),
        'Review objectives',
    );
    await page.locator('.agent-connection-role').fill('Check assessment');
    await page.locator('#agent-connections-search').fill('Writer');
    await page.locator('#agent-connections-list input[type=checkbox]').check();
    await page.locator('.agent-connection-role').fill('Write summary');
    const requestPromise = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    const request = await requestPromise;
    const body = request.postData();
    assert.match(body, /name="_method"\r\n\r\nPUT/);
    assert.match(body, /name="connections\[0\]\[agent_id\]"\r\n\r\n20/);
    assert.match(body, /Check assessment/);
    assert.match(body, /Write summary/);
    assert.equal(
        await page.locator('#agent-connections-list script').count(),
        0,
    );
});

test('hides connection controls when the emergency switch is off even with form layout styles', async (t) => {
    const page = await setup(t, { enabled: false });
    const response = page.waitForResponse((response) =>
        response.url().endsWith('connection-options'),
    );
    await page.locator('.edit-agent').click();
    await response;
    await page.locator('#agent-connections-field').waitFor({ state: 'hidden' });
    assert.equal(
        await page.locator('#agent-connections-field').isVisible(),
        false,
    );
});

test('submits explicit clearing when all connections are unchecked', async (t) => {
    const page = await setup(t, { saved: [{ agent_id: 20, role: 'Review' }] });
    await page.locator('.edit-agent').click();
    await page.getByText('1 / 10 Agents connected').waitFor();
    await page
        .locator('#agent-connections-list input[type=checkbox]')
        .first()
        .uncheck();
    const requestPromise = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    const body = (await requestPromise).postData();
    assert.match(body, /name="connections_configured"\r\n\r\n1/);
    assert.doesNotMatch(body, /name="connections\[/);
});

test('preserves existing connections if loading the choices fails', async (t) => {
    const page = await setup(t, {
        optionsFail: true,
        saved: [{ agent_id: 20, role: 'Review' }],
    });
    await page.locator('.edit-agent').click();
    await page
        .getByText('Connections could not be loaded.', { exact: false })
        .waitFor();
    const requestPromise = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    assert.doesNotMatch(
        (await requestPromise).postData(),
        /connections_configured|name="connections\[/,
    );
});

test('accepts ten selected Agents and prevents selecting an eleventh', async (t) => {
    const agents = Array.from({ length: 11 }, (_, index) => ({
        id: 100 + index,
        title: `Specialist ${index + 1}`,
    }));
    const page = await setup(t, { agents });
    await page.locator('.edit-agent').click();
    await page.getByText('0 / 10 Agents connected').waitFor();

    for (let index = 0; index < 10; index++) {
        await page
            .locator('#agent-connections-list input[type=checkbox]')
            .nth(index)
            .check();
    }

    await page.getByText('10 / 10 Agents connected').waitFor();
    await page
        .locator('#agent-connections-list input[type=checkbox]')
        .nth(10)
        .click();
    assert.equal(
        await page
            .locator('#agent-connections-list input[type=checkbox]:checked')
            .count(),
        10,
    );
    await page.getByText('Choose up to 10 connected Agents.').waitFor();
    const requestPromise = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    const body = (await requestPromise).postData();
    assert.match(body, /name="connections\[9\]\[agent_id\]"/);
    assert.doesNotMatch(body, /name="connections\[10\]\[agent_id\]"/);
});
