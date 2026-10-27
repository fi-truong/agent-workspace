import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { chromium } from 'playwright';

const script = await readFile(
    new URL('../../public/js/agent-workspace-chat.js', import.meta.url),
    'utf8',
);
const template = await readFile(
    new URL(
        '../../resources/views/ai-plus/agent-workspace/index.blade.php',
        import.meta.url,
    ),
    'utf8',
);
const styles = [...template.matchAll(/<style>([\s\S]*?)<\/style>/g)]
    .map((match) => match[1])
    .join('\n');
const themeStyles = await readFile(
    new URL('../../public/css/ai-plus.css', import.meta.url),
    'utf8',
);
const summary = template
    .match(/<div class="connected-agent-summary"[\s\S]*?<\/div>/)[0]
    .replace(/@if\(empty\(\$headerConnections\)\) hidden @endif/, 'hidden')
    .replace(/\{\{[\s\S]*?\}\}/g, '');
const routingSummary = template
    .match(/<div class="model-routing-summary"[\s\S]*?<\/div>/)[0]
    .replace(
        /@foreach\(\$chatModels[\s\S]*?@endforeach/,
        '<option value="gpt-6-luna" @selected($activeSpecialistModel === $modelId)>GPT-6 Luna · 1× credits</option><option value="gpt-6-sol" @selected($activeSpecialistModel === $modelId)>GPT-6 Sol · 20× credits</option>',
    )
    .replace(/@selected\([^)]*\)/g, '');
const composer = template
    .match(
        /<div class="input-box">[\s\S]*?<input type="file" id="chat-image-input"[^>]*>/,
    )[0]
    .replace(
        /@foreach\(\$chatModels[\s\S]*?@endforeach/,
        '<option value="gpt-6-luna">GPT-6 Luna · 1× credits</option><option value="gpt-6-sol">GPT-6 Sol · 20× credits</option>',
    );

async function setup(
    t,
    {
        selected = null,
        active = null,
        stored = null,
        conversation = false,
    } = {},
) {
    const browser = await chromium.launch({ headless: true });
    t.after(() => browser.close());
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    t.after(() => assert.deepEqual(errors, []));
    await page.route('http://workspace.test/**', async (route) => {
        if (route.request().method() === 'POST') {
            const request = route.request().postDataJSON();
            await new Promise((resolve) => setTimeout(resolve, 150));
            await route.fulfill({
                json: {
                    conversation_id: 9,
                    reply: 'School planning answer',
                    title: 'School planning',
                    user_message_id: 1,
                    token_quota: {
                        used: request.model === 'gpt-6-sol' ? 20000 : 1000,
                        limit: 20000000,
                        percentage: 0.1,
                        month_name: 'October',
                    },
                },
            });

            return;
        }

        await route.fulfill({
            contentType: 'text/html',
            body: `<!doctype html><html><head><meta charset="utf-8"><style>${themeStyles}body{margin:0}${styles}</style></head><body>
<div class="app"><main class="main"><div class="topbar"><div class="topbar-left">
${conversation ? `<div class="active-agent-breadcrumb">${active ? '<span class="agent-breadcrumb-name">Coordinator</span>' : '<span class="conversation-breadcrumb-name">Quick chat</span>'}</div>` : ''}
${summary}${routingSummary}
</div><div class="topbar-right"><button class="icon-btn">📎</button><button class="icon-btn">🤖</button><button class="icon-btn">↓</button></div></div>
<div class="empty-state"></div><div class="input-area"><div class="input-wrapper">${composer}</div></div>
<span data-token-quota-text></span>
</main></div></body></html>`,
        });
    });
    await page.goto(
        `http://workspace.test/ai-plus/agent-workspace${conversation ? '?conversation_id=9' : ''}`,
    );
    await page.evaluate(
        ({ selected, active, stored }) => {
            window.__SELECTED_AGENT_ID__ = selected;
            window.__ACTIVE_AGENT_ID__ = active;
            window.__MY_AGENTS__ = [
                { id: 2, title: 'Coordinator' },
                { id: 3, title: 'Standalone Agent' },
            ];
            window.__AGENT_CONNECTIONS__ = {
                2: [
                    { id: 20, title: 'Writer <img src=x onerror=alert(1)>' },
                    { id: 21, title: 'Lesson reviewer & curriculum planner' },
                ],
                3: [],
            };
            window.__INITIAL_MESSAGES__ = [];

            if (stored) {
                sessionStorage.setItem('selectedAgentId', stored);
            }
        },
        { selected, active, stored },
    );
    await page.addScriptTag({ content: script });
    await page.evaluate(() =>
        document.dispatchEvent(new Event('DOMContentLoaded')),
    );

    return page;
}

test('shows coordinator connections below the parent Agent and escapes titles', async (t) => {
    const page = await setup(t, { selected: 2 });
    const row = page.locator('[data-connected-agents]');
    assert.equal(await row.isVisible(), true);
    assert.equal(
        await row.locator('[data-connected-agent-names]').textContent(),
        'Writer <img src=x onerror=alert(1)> · Lesson reviewer & curriculum planner',
    );
    assert.equal(await row.locator('img').count(), 0);
    assert.equal(
        await row.evaluate((element) =>
            element.previousElementSibling.classList.contains(
                'active-agent-breadcrumb',
            ),
        ),
        true,
    );
    assert.equal(await page.locator('.active-agent-breadcrumb').count(), 1);
    assert.equal(
        await page.locator('[data-agent-model-routing]').isVisible(),
        true,
    );
    assert.match(
        await page.locator('[data-agent-model-routing]').textContent(),
        /Coordinator: GPT-6 Luna\s*·\s*Specialists use:/,
    );
    assert.equal(
        await page.locator('#specialist-model').inputValue(),
        'gpt-6-luna',
    );
    assert.equal(await page.locator('.topbar #chat-model').count(), 0);
    assert.equal(await page.locator('.input-box #chat-model').count(), 1);
});

test('restores connections for selections saved by Use in Chat', async (t) => {
    const page = await setup(t, { stored: '2' });
    assert.equal(
        await page.locator('[data-connected-agents]').isVisible(),
        true,
    );
});

test('an existing conversation uses its own coordinator instead of a stored selection', async (t) => {
    const page = await setup(t, {
        active: 2,
        stored: '3',
        conversation: true,
    });
    assert.equal(
        await page.locator('[data-connected-agents]').isVisible(),
        true,
    );
    assert.equal(await page.locator('.active-agent-breadcrumb').count(), 1);
});

test('keeps Quick Chat and standalone Agent headers free of connected Agent rows', async (t) => {
    for (const options of [
        {},
        { selected: 3 },
        { conversation: true, stored: '2' },
    ]) {
        const page = await setup(t, options);
        assert.equal(
            await page.locator('[data-connected-agents]').isVisible(),
            false,
        );
        assert.equal(
            await page.locator('[data-agent-model-routing]').isVisible(),
            false,
        );
    }
});

test('keeps long connection lists on one scrollable line without displacing toolbar actions', async (t) => {
    const page = await setup(t, { selected: 2 });

    for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 800 });
        const layout = await page
            .locator('[data-connected-agents]')
            .evaluate((element) => ({
                height: element.clientHeight,
                lineHeight: parseFloat(getComputedStyle(element).lineHeight),
                whiteSpace: getComputedStyle(element).whiteSpace,
                overflowX: getComputedStyle(element).overflowX,
                left: element.getBoundingClientRect().left,
                right: element.getBoundingClientRect().right,
                actionsLeft: document
                    .querySelector('.topbar-right')
                    .getBoundingClientRect().left,
            }));
        assert.equal(layout.whiteSpace, 'nowrap');
        assert.equal(layout.overflowX, 'auto');
        assert.ok(layout.height <= Math.ceil(layout.lineHeight));
        assert.ok(layout.right <= layout.actionsLeft);
        assert.ok(layout.left >= 0);
        assert.ok(layout.right <= width);
    }
});

test('sends the selected model, locks the picker while sending and displays credits', async (t) => {
    const page = await setup(t);
    await page.evaluate(() => {
        window.__SELECTED_AGENT_ID__ = 2;
        window.__AGENT_CONNECTIONS__[2] = [{ id: 20, title: 'Writer' }];
        document.dispatchEvent(new Event('DOMContentLoaded'));
    });
    await page.locator('#chat-model').selectOption('gpt-6-sol');
    await page.locator('#specialist-model').selectOption('gpt-6-sol');
    assert.match(
        await page.locator('[data-agent-model-routing]').textContent(),
        /Coordinator: GPT-6 Sol\s*·\s*Specialists use:/,
    );
    assert.equal(
        await page.locator('#specialist-model').inputValue(),
        'gpt-6-sol',
    );
    await page.locator('textarea').fill('Review the school plan');
    const requestPromise = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('.send-btn').click();
    const request = await requestPromise;
    assert.equal(request.postDataJSON().model, 'gpt-6-sol');
    assert.equal(request.postDataJSON().specialist_model, 'gpt-6-sol');
    assert.equal(await page.locator('#chat-model').isDisabled(), true);
    assert.equal(await page.locator('#specialist-model').isDisabled(), true);
    assert.equal(
        await page.locator('.send-btn').getAttribute('aria-busy'),
        'true',
    );
    assert.equal(await page.locator('.send-icon').isVisible(), false);
    assert.equal(await page.locator('.send-spinner').isVisible(), true);
    assert.equal((await page.locator('.send-btn').textContent()).trim(), '');
    await page.getByText('School planning answer', { exact: true }).waitFor();
    assert.equal(await page.locator('#chat-model').isEnabled(), true);
    assert.equal(await page.locator('#specialist-model').isEnabled(), true);
    assert.equal(await page.locator('.send-icon').isVisible(), true);
    assert.equal(
        await page.locator('.send-btn').getAttribute('aria-label'),
        'Send message',
    );
    assert.equal(
        await page.locator('.send-btn').getAttribute('aria-busy'),
        null,
    );
    assert.match(
        await page.locator('[data-token-quota-text]').textContent(),
        /20 \/ 20,000 credits used/,
    );
    await page.locator('#chat-model').selectOption('gpt-6-luna');
    await page.locator('textarea').fill('Shorten the school plan');
    const nextRequest = page.waitForRequest(
        (request) => request.method() === 'POST',
    );
    await page.locator('.send-btn').click();
    const nextPayload = (await nextRequest).postDataJSON();
    assert.equal(nextPayload.model, 'gpt-6-luna');
    assert.equal(nextPayload.specialist_model, 'gpt-6-sol');
});

test('keeps the model picker visible and readable in all four themes', async (t) => {
    const page = await setup(t);

    for (const theme of ['', 'teal', 'orange', 'burgundy']) {
        await page.evaluate((theme) => {
            document.documentElement.setAttribute('data-theme', theme);
        }, theme);

        for (const width of [320, 390, 1280]) {
            await page.setViewportSize({ width, height: 800 });
            const layout = await page
                .locator('#chat-model')
                .evaluate((element) => ({
                    model: element.getBoundingClientRect().toJSON(),
                    attach: document
                        .querySelector('.attach-btn')
                        .getBoundingClientRect()
                        .toJSON(),
                    send: document
                        .querySelector('.send-btn')
                        .getBoundingClientRect()
                        .toJSON(),
                    textarea: document
                        .querySelector('textarea')
                        .getBoundingClientRect()
                        .toJSON(),
                    text: getComputedStyle(element).color,
                    background: getComputedStyle(element).backgroundColor,
                }));
            assert.notEqual(layout.text, layout.background);
            assert.ok(layout.attach.right < layout.model.left);
            assert.ok(layout.model.right < layout.send.left);
            assert.ok(layout.send.right <= width);
            assert.ok(layout.attach.left >= 0);
            assert.ok(layout.model.width >= 120);
            assert.ok(layout.textarea.bottom <= layout.attach.top);
            assert.ok(layout.textarea.bottom <= layout.model.top);
            assert.ok(layout.textarea.bottom <= layout.send.top);
        }
    }
});

test('grows and shrinks the prompt while keeping the toolbar below it', async (t) => {
    const page = await setup(t);
    const textarea = page.locator('textarea');
    await textarea.fill('Short prompt');
    const short = await textarea.boundingBox();
    await textarea.fill('A detailed school planning prompt.\n'.repeat(12));
    const long = await textarea.boundingBox();
    assert.ok(long.height > short.height);
    const toolbar = await page.locator('.input-actions').boundingBox();
    assert.ok(toolbar.y >= long.y + long.height);
    await textarea.fill('Short again');
    assert.equal((await textarea.boundingBox()).height, short.height);
});

test('keeps the arrow button intact while reading attachments', async (t) => {
    const page = await setup(t);
    await page.evaluate(() => {
        const NativeReader = window.FileReader;
        window.FileReader = class extends NativeReader {
            readAsDataURL(file) {
                setTimeout(() => super.readAsDataURL(file), 300);
            }
        };
    });
    await page.locator('#chat-image-input').setInputFiles({
        name: 'school-plan.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('Synthetic school plan'),
    });
    assert.equal(
        await page.locator('.send-btn').getAttribute('aria-label'),
        'Preparing attachments',
    );
    assert.equal(await page.locator('.send-spinner').isVisible(), true);
    await page.locator('.send-btn:not(:disabled)').waitFor();
    assert.equal(await page.locator('.send-icon').isVisible(), true);
    assert.equal((await page.locator('.send-btn').textContent()).trim(), '');
});
