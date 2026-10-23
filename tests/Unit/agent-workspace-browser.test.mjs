import assert from 'node:assert/strict';
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
const summary = template
    .match(/<div class="connected-agent-summary"[\s\S]*?<\/div>/)[0]
    .replace(/@if\(empty\(\$headerConnections\)\) hidden @endif/, 'hidden')
    .replace(/\{\{[\s\S]*?\}\}/g, '');

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
    await page.route('http://workspace.test/**', (route) =>
        route.fulfill({
            contentType: 'text/html',
            body: `<!doctype html><html><head><style>body{margin:0}${styles}</style></head><body>
<div class="app"><main class="main"><div class="topbar"><div class="topbar-left">
<div class="model-selector">GPT-6 Luna<span class="badge">School AI</span></div>${summary}
${conversation ? `<div class="active-agent-breadcrumb">${active ? '<span class="agent-breadcrumb-name">Coordinator</span>' : '<span class="conversation-breadcrumb-name">Quick chat</span>'}</div>` : ''}
</div><div class="topbar-right"><button class="icon-btn">📎</button><button class="icon-btn">🤖</button><button class="icon-btn">↓</button></div></div>
<div class="empty-state"></div><div class="input-area"><div class="input-box"><textarea></textarea><button class="send-btn">Send</button></div></div>
</main></div></body></html>`,
        }),
    );
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

test('shows a new coordinator connections directly below the model and escapes titles', async (t) => {
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
            element.previousElementSibling.classList.contains('model-selector'),
        ),
        true,
    );
    assert.equal(await page.locator('.active-agent-breadcrumb').count(), 1);
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
