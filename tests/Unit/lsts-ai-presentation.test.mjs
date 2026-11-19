import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { chromium } from 'playwright';

const read = (path) =>
    readFile(new URL('../../' + path, import.meta.url), 'utf8');
const [base, image, presentation, workspace, agents, chat, themes] =
    await Promise.all([
        read('public/css/ai-plus.css'),
        read('public/css/image-workspace.css'),
        read('public/css/lsts-ai-plus.css'),
        read('public/css/lsts-agent-workspace.css'),
        read('resources/views/ai-plus/agent-workspace/agents/index.blade.php'),
        read('resources/views/ai-plus/agent-workspace/index.blade.php'),
        read('resources/views/partials/theme-switcher.blade.php'),
    ]);
const styles = (markup) =>
    [...markup.matchAll(/<style>([\s\S]*?)<\/style>/g)]
        .map((match) => match[1])
        .join('\n');

async function pageFor(t, width) {
    const browser = await chromium.launch({ headless: true });
    t.after(() => browser.close());

    return browser.newPage({ viewport: { width, height: 740 } });
}

test('lays out Agent records in rows and keeps actions inside them at narrow widths', async (t) => {
    const page = await pageFor(t, 1024);
    await page.setContent(
        '<style>' +
            base +
            styles(agents) +
            presentation +
            workspace +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-agents"><main class="agents-grid"><article class="agent-card">' +
            '<div class="agent-card-header"><div class="agent-title">A long example Agent name on two lines</div></div>' +
            '<p class="agent-classification">School Operations · Academic Scheduling & Coordination</p>' +
            '<div class="agent-description-wrap"><p class="agent-description is-collapsed">' +
            'A descriptive paragraph about a shared Agent. '.repeat(8) +
            '</p><button class="agent-description-toggle">More</button></div>' +
            '<div class="agent-meta"><span class="badge shared">Shared · Multiple audiences</span><span class="updated-at">Updated 5 hours ago</span></div>' +
            '<div class="agent-actions"><button class="btn-icon use-agent">Use in Chat</button><button class="btn-icon edit-agent">Edit</button></div></article></main></body>',
    );
    const title = page.locator('.agent-title');
    const actions = page.locator('.agent-actions');
    assert.match(
        await title.evaluate((element) => getComputedStyle(element).fontFamily),
        /^system-ui/,
    );
    assert.ok((await title.boundingBox()).x < (await actions.boundingBox()).x);

    await page.setViewportSize({ width: 320, height: 740 });
    const cardBox = await page.locator('.agent-card').boundingBox();
    const actionBox = await actions.boundingBox();
    assert.ok(actionBox.y > (await title.boundingBox()).y);
    assert.ok(actionBox.y + actionBox.height < cardBox.y + cardBox.height);
    assert.ok(actionBox.x + actionBox.width < cardBox.x + cardBox.width);
});

test('keeps Image Studio history available on mobile and the theme picker clear of Generate', async (t) => {
    const page = await pageFor(t, 1440);
    await page.setContent(
        '<style>' +
            base +
            image +
            presentation +
            workspace +
            styles(themes) +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-image"><header class="ai-plus-topbar">LSTS AI+</header>' +
            '<div class="image-workspace"><aside class="image-sidebar"><div class="image-history">Image history</div></aside>' +
            '<main class="image-main"><section class="image-results"></section><form class="image-composer">' +
            '<div class="image-composer-row"><textarea></textarea><select><option>Flare</option></select>' +
            '<button class="image-attach-button">Add image</button><button id="image-generate-submit">Generate image</button>' +
            '</div></form></main></div><div class="theme-switcher"><button class="theme-swatch">Mint</button></div></body>',
    );

    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 740 });
        const action = await page
            .locator('#image-generate-submit')
            .boundingBox();
        const themes = await page.locator('.theme-switcher').boundingBox();
        assert.ok(action.x + action.width < themes.x);
        const prompt = await page
            .locator('.image-composer-row textarea')
            .boundingBox();
        assert.ok(prompt.y + prompt.height <= action.y);
        assert.equal(await page.locator('.image-history').isVisible(), true);
    }
});

test('uses two editor columns on desktop, stacks them on mobile and keeps Save visible', async (t) => {
    const page = await pageFor(t, 1440);
    await page.setContent(
        '<style>' +
            base +
            styles(agents) +
            presentation +
            workspace +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-agents"><div id="agent-modal" class="modal-overlay"><div class="modal">' +
            '<header class="modal-header"><h3>Create Agent</h3></header><form id="agent-form">' +
            '<section class="agent-form-panel"><h4>Agent details</h4><label for="title">Title</label><input id="title"></section>' +
            '<section class="agent-form-panel"><h4>Instructions and resources</h4>' +
            '<div class="form-group"><label>A field</label><textarea rows="8"></textarea></div>'.repeat(
                8,
            ) +
            '</section>' +
            '<div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></body>',
    );
    const [details, resources] = await page.locator('.agent-form-panel').all();
    assert.ok(
        (await resources.boundingBox()).x > (await details.boundingBox()).x,
    );

    await page.setViewportSize({ width: 390, height: 740 });
    assert.ok(
        (await resources.boundingBox()).y > (await details.boundingBox()).y,
    );

    for (const width of [390, 1440]) {
        await page.setViewportSize({ width, height: 740 });
        const modal = await page.locator('.modal').boundingBox();
        const save = await page
            .getByRole('button', { name: 'Save' })
            .boundingBox();
        assert.ok(
            save.y >= modal.y && save.y + save.height <= modal.y + modal.height,
        );
    }
});

test('aligns Chat messages and the composer and uses vertical navigation on desktop', async (t) => {
    const page = await pageFor(t, 1280);
    await page.setContent(
        '<style>' +
            base +
            styles(chat) +
            presentation +
            workspace +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-chat"><div class="app"><aside class="sidebar">' +
            '<nav class="workspace-tabs"><a class="ws-tab active" href="#">Chat</a><a class="ws-tab" href="#">Agents</a></nav></aside>' +
            '<main class="main"><div id="chat-messages" style="padding:24px;display:flex;flex-direction:column">' +
            '<div class="chat-msg-wrap" style="max-width:85%;align-self:flex-start;"><div class="chat-bubble">Assistant reply</div></div></div>' +
            '<div class="input-area"><div class="input-wrapper"><textarea></textarea></div></div></main></div></body>',
    );
    const tabs = page.locator('.workspace-tabs');
    assert.equal(
        await tabs.evaluate(
            (element) => getComputedStyle(element).flexDirection,
        ),
        'column',
    );

    await page.setViewportSize({ width: 390, height: 740 });
    assert.equal(
        await tabs.evaluate(
            (element) => getComputedStyle(element).flexDirection,
        ),
        'row',
    );

    for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 740 });
        const reply = await page.locator('.chat-msg-wrap').boundingBox();
        const composer = await page.locator('.input-wrapper').boundingBox();
        assert.ok(Math.abs(reply.x - composer.x) < 1);
        assert.ok(Math.abs(reply.width - composer.width) < 1);
    }
});
