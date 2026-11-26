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
const classificationFields = modalTemplate
    .slice(
        modalTemplate.indexOf('<div class="agent-classification-fields">'),
        modalTemplate.indexOf('<div class="form-group agent-avatar-field">'),
    )
    .replace(
        /@foreach\([\s\S]*?@endforeach/,
        '<option value="teaching">Teaching</option><option value="school_operations">School Operations</option>',
    );
const fixture = `<!doctype html><html><head><meta name="csrf-token" content="test"><style>.form-group{display:flex;flex-direction:column}${modalStyles}</style></head><body>
<button id="create-agent-btn">Create</button><div id="agents-grid"><button class="edit-agent" data-agent-id="10">Edit</button></div>
<div id="agent-modal" style="display:none"><h3 id="modal-title"></h3><button id="modal-close">Close</button>
<form id="agent-form"><input id="form-method" name="_method"><input id="agent-id" name="agent_id">
<input id="title" name="title" required><textarea id="description"></textarea><textarea id="system_prompt"></textarea>
<input type="hidden" id="agent-template-id" name="template_id">
${classificationFields}
<input type="checkbox" id="is_shared" name="is_shared" value="1">
<div id="sharing-audience-group" hidden><label class="sharing-audience-choice"><input type="checkbox" id="sharing-school" name="sharing_scopes[]" value="school" checked>Everyone at school</label><label class="sharing-audience-choice"><input type="checkbox" id="sharing-department" name="sharing_scopes[]" value="department">My department</label><label class="sharing-audience-choice"><input type="checkbox" id="sharing-users" name="sharing_scopes[]" value="users">Specific people</label><div id="sharing-people-picker" hidden><input id="sharing-user-search" type="search"></div><small id="sharing-department-hint" hidden></small><div id="sharing-search-status"></div><div id="sharing-search-results"></div><div id="sharing-selected-users"></div></div><fieldset id="sharing-access-group" hidden><input type="radio" name="sharing_access" value="use_only" checked><input type="radio" name="sharing_access" value="copy"></fieldset>
<fieldset id="agent-connections-field" class="form-group agent-connections-field" hidden><input id="agent-connections-search" type="search"><div id="agent-connections-list"></div><div id="agent-connections-status"></div></fieldset>
<button type="button" id="modal-cancel">Cancel</button><button type="submit" id="modal-submit">Save</button></form></div></body></html>`;

async function setup(
    t,
    {
        optionsFail = false,
        enabled = true,
        saved = [],
        agents = null,
        sharing = {},
    } = {},
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

        if (url.pathname.endsWith('sharing-users')) {
            await route.fulfill({
                json: {
                    users: [
                        {
                            id: 41,
                            name: 'Example Colleague',
                            email: 'example@example.test',
                            department: 'HR',
                        },
                        {
                            id: 42,
                            name: 'Another <img src=x onerror=bad()>',
                            email: 'another@example.test',
                        },
                    ],
                },
            });
        } else if (url.pathname.endsWith('connection-options')) {
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
                json: {
                    id: 10,
                    title: 'Coordinator',
                    connections: saved,
                    ...sharing,
                },
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
            sharingUsersUrl: '/ai-plus/agent-workspace/agents/sharing-users',
            department: 'CIEC',
            maxConnections: 10,
            categories: {
                teaching: {
                    lesson_planning: 'Lesson Planning',
                    other: 'Other',
                },
                school_operations: {
                    budgeting_procurement: 'Budgeting & Procurement',
                    other: 'Other',
                },
            },
        };
    });
    await page.addScriptTag({ content: script });
    await page.evaluate(() =>
        document.dispatchEvent(new Event('DOMContentLoaded')),
    );

    return page;
}

test('requires the new Agent category and resets it when the group changes', async (t) => {
    const page = await setup(t);
    await page.locator('#create-agent-btn').click();
    await page.locator('#title').fill('Planning Agent');
    const group = page.locator('#agent-category-group');
    const category = page.locator('#agent-category');
    assert.equal(await category.isDisabled(), true);
    await group.selectOption('teaching');
    await category.selectOption('lesson_planning');
    await group.selectOption('school_operations');
    assert.equal(await category.inputValue(), '');
    assert.equal(
        await category.locator('option[value=lesson_planning]').count(),
        0,
    );
    assert.equal(
        await category.evaluate((select) => select.validity.valueMissing),
        true,
    );
});

test('keeps the guidance directly below Category on desktop and mobile', async (t) => {
    const page = await setup(t);
    await page.locator('#create-agent-btn').click();
    const category = page.locator('#agent-category');
    const hint = page.locator('#agent-category + #agent-category-hint');
    await hint.waitFor();

    for (const width of [1024, 390]) {
        await page.setViewportSize({ width, height: 800 });
        const field = await category.boundingBox();
        const guidance = await hint.boundingBox();
        assert.equal(guidance.x, field.x);
        assert.ok(guidance.y >= field.y + field.height);
        assert.ok(guidance.width <= field.width);
    }
});

test('selects Agents, keeps roles during search, and submits multipart edit using POST', async (t) => {
    const page = await setup(t, {
        saved: [{ agent_id: 20, role: 'Review objectives' }],
    });
    await page.locator('.edit-agent').click();
    await page.getByText('1 Agents connected').waitFor();
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
    await page.getByText('1 Agents connected').waitFor();
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

test('accepts any number of selected Agents', async (t) => {
    const agents = Array.from({ length: 11 }, (_, index) => ({
        id: 100 + index,
        title: `Specialist ${index + 1}`,
    }));
    const page = await setup(t, { agents });
    await page.locator('.edit-agent').click();
    await page.getByText('0 Agents connected').waitFor();

    for (let index = 0; index < 11; index++) {
        await page
            .locator('#agent-connections-list input[type=checkbox]')
            .nth(index)
            .check();
    }

    await page.getByText('11 Agents connected').waitFor();
    assert.equal(
        await page
            .locator('#agent-connections-list input[type=checkbox]:checked')
            .count(),
        11,
    );
    const requestPromise = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    const body = (await requestPromise).postData();
    assert.match(body, /name="connections\[10\]\[agent_id\]"/);
});

test('shows the people search beside the audience and submits only selected recipient IDs', async (t) => {
    const page = await setup(t);
    await page.locator('#create-agent-btn').click();
    await page.locator('#title').fill('Shared planner');
    await page.locator('#agent-category-group').selectOption('teaching');
    await page.locator('#agent-category').selectOption('lesson_planning');
    await page.locator('#is_shared').check();
    await page.locator('#sharing-school').uncheck();
    await page.locator('#sharing-users').check();
    assert.equal(
        await page.locator('#sharing-people-picker').isVisible(),
        true,
    );
    await page.locator('#sharing-user-search').fill('ex');
    await page
        .getByRole('button', {
            name: 'Example Colleague example@example.test · HR',
        })
        .click();
    await page.locator('#sharing-user-search').fill('an');
    await page
        .getByRole('button', {
            name: 'Another <img src=x onerror=bad()> another@example.test',
        })
        .click();
    assert.equal(await page.locator('#sharing-selected-users img').count(), 0);
    await page
        .getByRole('button', { name: 'Remove Example Colleague', exact: true })
        .click();
    const request = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    const sent = (await request).postData();
    assert.match(sent, /name="sharing_scopes\[\]"\r\n\r\nusers/);
    assert.match(sent, /name="sharing_recipients_configured"\r\n\r\n1/);
    assert.match(sent, /name="sharing_user_ids\[\]"\r\n\r\n42/);
    assert.doesNotMatch(sent, /name="sharing_user_ids\[\]"\r\n\r\n41/);
});

test('keeps a selected person when adding department sharing and clears them only when unchecked', async (t) => {
    const page = await setup(t, {
        sharing: {
            is_shared: true,
            sharing_access: 'use_only',
            sharing_scope: 'users',
            sharing_users: [
                {
                    id: 41,
                    name: 'Example Colleague',
                    email: 'example@example.test',
                },
            ],
        },
    });
    await page.locator('.edit-agent').click();
    await page
        .locator('#sharing-selected-users')
        .getByText('Example Colleague · example@example.test')
        .waitFor();
    assert.equal(await page.locator('#sharing-users').isChecked(), true);
    await page.locator('#sharing-department').check();
    assert.equal(
        await page.locator('#sharing-people-picker').isVisible(),
        true,
    );
    assert.equal(
        await page.locator('#sharing-department-hint').innerText(),
        '· CIEC',
    );
    const request = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    const sent = (await request).postData();
    assert.match(sent, /name="sharing_scopes\[\]"\r\n\r\ndepartment/);
    assert.match(sent, /name="sharing_scopes\[\]"\r\n\r\nusers/);
    assert.match(sent, /name="sharing_user_ids\[\]"\r\n\r\n41/);

    // Dismiss the fixture's validation notice before editing again.
    await page
        .getByText('Test validation response keeps this form open.')
        .waitFor();
    await page
        .getByRole('button', { name: 'Close', exact: true })
        .last()
        .click();
    // Unchecking people is the explicit removal action; simply adding a unit must preserve them.
    await page.locator('#sharing-users').uncheck();
    assert.equal(
        await page.locator('#sharing-people-picker').isVisible(),
        false,
    );
    const removedRequest = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('#modal-submit').click();
    assert.doesNotMatch(
        (await removedRequest).postData(),
        /name="sharing_user_ids/,
    );
});
