import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { chromium } from 'playwright';

const read = (path) =>
    readFile(new URL('../../' + path, import.meta.url), 'utf8');
const [base, image, presentation, agents, themes] = await Promise.all([
    read('public/css/ai-plus.css'),
    read('public/css/image-workspace.css'),
    read('public/css/lsts-ai-plus.css'),
    read('resources/views/ai-plus/agent-workspace/agents/index.blade.php'),
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

test('keeps actions inside Agent cards that have a long title and classification', async (t) => {
    const page = await pageFor(t, 1024);
    await page.setContent(
        '<style>' +
            base +
            styles(agents) +
            presentation +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-agents"><article class="agent-card" style="width:302px">' +
            '<div class="agent-card-header"><div class="agent-title">A long example Agent name on two lines</div></div>' +
            '<p class="agent-classification">School Operations · Academic Scheduling & Coordination</p>' +
            '<div class="agent-description-wrap"><p class="agent-description is-collapsed">' +
            'A descriptive paragraph about a shared Agent. '.repeat(8) +
            '</p><button class="agent-description-toggle">More</button></div>' +
            '<div class="agent-meta"><span class="badge shared">Shared · Multiple audiences</span><span class="updated-at">Updated 5 hours ago</span></div>' +
            '<div class="agent-actions"><button class="btn-icon use-agent">Use in Chat</button><button class="btn-icon edit-agent">Edit</button></div></article></body>',
    );
    const card = await page.locator('.agent-card').boundingBox();
    const actions = await page.locator('.agent-actions').boundingBox();
    assert.ok(actions.y + actions.height < card.y + card.height);
});

test('keeps Image Studio history available on mobile and the theme picker clear of Generate', async (t) => {
    const page = await pageFor(t, 1440);
    await page.setContent(
        '<style>' +
            base +
            image +
            presentation +
            styles(themes) +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-image"><header class="ai-plus-topbar">LSTS AI+</header>' +
            '<div class="image-workspace"><aside class="image-sidebar"><div class="image-history">Image history</div></aside>' +
            '<main class="image-main"><section class="image-results"></section><form class="image-composer">' +
            '<div class="image-composer-row"><textarea></textarea><select><option>Flare</option></select>' +
            '<button class="image-attach-button">Add image</button><button id="image-generate-submit">Generate image</button>' +
            '</div></form></main></div><div class="theme-switcher"><button class="theme-swatch">Mint</button></div></body>',
    );

    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 740 });
        const action = await page
            .locator('#image-generate-submit')
            .boundingBox();
        const themes = await page.locator('.theme-switcher').boundingBox();
        assert.ok(action.x + action.width < themes.x);
        assert.equal(await page.locator('.image-history').isVisible(), true);
    }
});

test('keeps Save inside the visible Agent modal at the top of a long form', async (t) => {
    const page = await pageFor(t, 390);
    await page.setContent(
        '<style>' +
            base +
            presentation +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-agents"><div id="agent-modal" class="modal-overlay"><div class="modal">' +
            '<header class="modal-header"><h3>Create Agent</h3></header><form id="agent-form">' +
            '<div class="form-group"><label>A field</label><textarea rows="8"></textarea></div>'.repeat(
                8,
            ) +
            '<div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></body>',
    );
    const modal = await page.locator('.modal').boundingBox();
    const save = await page.getByRole('button', { name: 'Save' }).boundingBox();
    assert.ok(
        save.y >= modal.y && save.y + save.height < modal.y + modal.height,
    );
});
