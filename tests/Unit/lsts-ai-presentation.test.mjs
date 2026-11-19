import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { chromium } from 'playwright';

const read = (path) =>
    readFile(new URL('../../' + path, import.meta.url), 'utf8');
const [
    typography,
    aiPlus,
    image,
    presentation,
    workspace,
    sections,
    agents,
    chat,
    themes,
] = await Promise.all([
    read('public/css/lsts-typography.css'),
    read('public/css/ai-plus.css'),
    read('public/css/image-workspace.css'),
    read('public/css/lsts-ai-plus.css'),
    read('public/css/lsts-agent-workspace.css'),
    read('public/css/lsts-ai-sections.css'),
    read('resources/views/ai-plus/agent-workspace/agents/index.blade.php'),
    read('resources/views/ai-plus/agent-workspace/index.blade.php'),
    read('resources/views/partials/theme-switcher.blade.php'),
]);
const base = typography + aiPlus;
const styles = (markup) =>
    [...markup.matchAll(/<style>([\s\S]*?)<\/style>/g)]
        .map((match) => match[1])
        .join('\n');

async function pageFor(t, width) {
    const browser = await chromium.launch({ headless: true });
    t.after(() => browser.close());

    return browser.newPage({ viewport: { width, height: 740 } });
}

test('keeps the homepage header height stable across quotes on desktop and mobile', async (t) => {
    const page = await pageFor(t, 1280);
    const [homepage, catalog, font] = await Promise.all([
        read('resources/views/ai-plus/index.blade.php'),
        read('app/Support/HomepageQuotes.php'),
        readFile(
            new URL('../../public/fonts/inter-var.woff2', import.meta.url),
        ),
    ]);
    const hero = homepage.match(/<header class="hero">[\s\S]*?<\/header>/)?.[0];
    assert.ok(hero);
    const quotes = Array.from(
        catalog.matchAll(
            /'text' => '(.*?)',\s*'name' => '(.*?)',\s*'role' => '(.*?)',/g,
        ),
        ([, text, name, role]) => ({ text, name, role }),
    );
    assert.ok(quotes.length >= 10);
    const fontCss = typography.replace(
        /url\([^)]+\)/,
        `url(data:font/woff2;base64,${font.toString('base64')})`,
    );
    await page.setContent(
        `<style>${fontCss}${aiPlus}${presentation}</style><body class="lsts-ai-ui lsts-ai-home">${hero}</body>`,
    );
    await page.evaluate(() => document.fonts.ready);

    const header = page.locator('.hero');
    const title = page.getByRole('heading', { name: 'LSTS AI+' });
    const description = page.locator('.hero .sub');
    const quote = page.locator('.hero-quote');
    assert.equal(await page.locator('.hero a').count(), 0);
    assert.equal(
        await title.evaluate((heading) => getComputedStyle(heading).fontWeight),
        '750',
    );

    for (const width of [1440, 1280, 1024, 800, 799, 390, 320]) {
        await page.setViewportSize({ width, height: 740 });
        const heights = await header.evaluate((element, samples) => {
            const quotation = element.querySelector('blockquote');
            const name = element.querySelector('.hero-quote-name');
            const role = element.querySelector('.hero-quote-role');

            return samples.map((sample) => {
                quotation.textContent = `“${sample.text}”`;
                name.textContent = sample.name;
                role.textContent = sample.role;

                return element.getBoundingClientRect().height;
            });
        }, quotes);
        assert.equal(
            new Set(heights).size,
            1,
            `Height changed at ${width}px: ${heights.join(', ')}`,
        );

        const titleBox = await title.boundingBox();
        const descriptionBox = await description.boundingBox();
        const quoteBox = await quote.boundingBox();

        if (width >= 800) {
            assert.ok(titleBox.x + titleBox.width < quoteBox.x);
            assert.ok(quoteBox.width > titleBox.width);
        } else {
            assert.equal(titleBox.x, quoteBox.x);
            assert.ok(descriptionBox.y + descriptionBox.height < quoteBox.y);
        }

        assert.equal(titleBox.x, descriptionBox.x);
        assert.ok(titleBox.y + titleBox.height < descriptionBox.y);
        assert.equal(
            await page.evaluate(() => document.documentElement.scrollWidth),
            width,
        );
    }
});

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
        /^Inter/,
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

test('renders Vietnamese headings and controls with the same bundled Inter font', async (t) => {
    const page = await pageFor(t, 1024);
    const font = await readFile(
        new URL('../../public/fonts/inter-var.woff2', import.meta.url),
    );
    await page.route('https://lsts.test/fonts/inter-var.woff2?*', (route) =>
        route.fulfill({ contentType: 'font/woff2', body: font }),
    );
    await page.route('https://lsts.test/css/lsts-typography.css', (route) =>
        route.fulfill({ contentType: 'text/css', body: typography }),
    );
    await page.route('https://lsts.test/', (route) =>
        route.fulfill({
            contentType: 'text/html; charset=utf-8',
            body:
                '<link rel="stylesheet" href="/css/lsts-typography.css"><body class="lsts-ai-ui">' +
                '<h1>Chính sách và Hướng dẫn sử dụng AI</h1><button>Tiếng Việt</button></body>',
        }),
    );

    await page.goto('https://lsts.test/');
    await page.evaluate(() => document.fonts.ready);
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('DOM.enable');
    await cdp.send('CSS.enable');
    const { root } = await cdp.send('DOM.getDocument');

    for (const selector of ['h1', 'button']) {
        const { nodeId } = await cdp.send('DOM.querySelector', {
            nodeId: root.nodeId,
            selector,
        });
        const { fonts } = await cdp.send('CSS.getPlatformFontsForNode', {
            nodeId,
        });
        assert.equal(fonts.length, 1);
        assert.match(fonts[0].familyName, /^Inter/);
        assert.equal(fonts[0].isCustomFont, true);
    }
});

test('keeps section navigation and catalog rows usable across mobile and desktop', async (t) => {
    const page = await pageFor(t, 1440);
    const prompts = await read(
        'resources/views/ai-plus/prompt-library/index.blade.php',
    );
    await page.setContent(
        '<style>' +
            base +
            styles(prompts) +
            presentation +
            sections +
            '</style>' +
            '<body class="lsts-ai-ui lsts-ai-catalog"><div class="lsts-section-shell">' +
            '<aside class="lsts-section-sidebar"><nav class="lsts-section-nav">' +
            '<a href="#">Agent Workspace</a><a href="#" aria-current="page">Prompt Library</a>' +
            '<a href="#">AI Policy &amp; Guidelines</a></nav></aside>' +
            '<div class="lsts-section-content"><header class="page-header"><div class="wrap"><h1>Prompt Library</h1></div></header>' +
            '<main class="content"><div class="wrap"><div class="prompt-grid"><article class="prompt-card">' +
            '<div class="prompt-summary"><h2 class="prompt-title">Plan an interdisciplinary lesson</h2></div>' +
            '<div class="prompt-content"><p class="prompt-preview">A topic and measurable outcomes</p>' +
            '<details class="prompt-details"><summary>View full prompt</summary><div class="prompt-full-text">Specify context and the desired output.</div></details></div>' +
            '<div class="prompt-footer"><button class="copy-btn">Copy Prompt</button></div>' +
            '</article></div></div></main></div></div></body>',
    );

    const nav = page.locator('.lsts-section-nav');
    const heading = page.getByRole('heading', { name: 'Prompt Library' });
    const summary = page.locator('.prompt-summary');
    const copy = page.getByRole('button', { name: 'Copy Prompt' });
    assert.ok((await nav.boundingBox()).x < (await heading.boundingBox()).x);
    assert.ok((await summary.boundingBox()).x < (await copy.boundingBox()).x);

    await page.setViewportSize({ width: 360, height: 780 });
    const navBox = await nav.boundingBox();
    assert.ok(navBox.y + navBox.height <= (await heading.boundingBox()).y);
    assert.ok((await summary.boundingBox()).y < (await copy.boundingBox()).y);
    await page.getByText('View full prompt').click();
    assert.equal(await page.locator('.prompt-preview').isVisible(), false);
    assert.equal(
        await page
            .getByText('Specify context and the desired output.')
            .isVisible(),
        true,
    );
    assert.equal(
        await page.evaluate(() => document.documentElement.scrollWidth),
        360,
    );
});

test('reflows the template, showcase, policy, support and usage layouts on mobile', async (t) => {
    const page = await pageFor(t, 1440);
    const cases = [
        {
            view: 'agent-templates/index',
            screen: 'catalog',
            content:
                '<div class="template-grid"><article class="template-card"><div class="template-preview">T</div>' +
                '<div class="template-body"><h3 class="template-title">Classroom Planner</h3><p>Describe an activity.</p></div>' +
                '<div class="template-footer"><button class="use-btn">Use Template</button></div></article></div>',
            left: '.template-body',
            right: '.template-footer',
        },
        {
            view: 'sharing-showcase/index',
            screen: 'catalog',
            content:
                '<div class="showcase-grid"><article class="showcase-card"><div class="showcase-header">Teaching Team</div>' +
                '<div class="showcase-body"><h2>Planning a school activity</h2><a class="view-btn" href="#">View Details</a></div></article></div>',
            left: '.showcase-header',
            right: '.showcase-body',
        },
        {
            view: 'ai-policy/index',
            screen: 'document',
            content:
                '<div class="wrap policy-reading-layout"><nav class="toc-nav"><div class="wrap"><div class="toc-links">' +
                '<a class="toc-link" href="#overview-vi">Tổng quan</a></div></div></nav><main class="content"><div class="wrap">' +
                '<section class="section" id="overview-vi"><h2>Tổng quan</h2></section></div></main></div>',
            left: '.policy-reading-layout .content',
            right: '.toc-nav',
            rightFirstOnMobile: true,
        },
        {
            view: 'support/index',
            screen: 'document',
            content:
                '<div class="wrap support-grid"><div class="faq-section"><h2>Frequently Asked Questions</h2></div>' +
                '<div class="contact-section"><h2>Submit a Request</h2><input class="form-input" aria-label="Subject"></div></div>',
            left: '.faq-section',
            right: '.contact-section',
        },
        {
            view: 'my-usage/index',
            screen: 'document',
            content:
                '<div class="two-col"><div class="activity-section"><h2>Recent Activity</h2></div>' +
                '<div class="top-tasks"><h2>Top Task Types</h2></div></div>',
            left: '.activity-section',
            right: '.top-tasks',
        },
    ];

    for (const {
        view,
        screen,
        content,
        left,
        right,
        rightFirstOnMobile,
    } of cases) {
        const markup = await read(`resources/views/ai-plus/${view}.blade.php`);
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.setContent(
            '<style>' +
                base +
                styles(markup) +
                presentation +
                sections +
                '</style>' +
                `<body class="lsts-ai-ui lsts-ai-${screen}"><div class="lsts-section-shell"><aside class="lsts-section-sidebar"></aside>` +
                `<div class="lsts-section-content">${content}</div></div></body>`,
        );

        const leftPanel = page.locator(left);
        const rightPanel = page.locator(right);
        assert.ok(
            (await leftPanel.boundingBox()).x <
                (await rightPanel.boundingBox()).x,
            view,
        );

        await page.setViewportSize({ width: 360, height: 780 });
        const [first, second] = rightFirstOnMobile
            ? [rightPanel, leftPanel]
            : [leftPanel, rightPanel];
        assert.ok(
            (await first.boundingBox()).y < (await second.boundingBox()).y,
            view,
        );
        assert.equal(
            await page.evaluate(() => document.documentElement.scrollWidth),
            360,
            view,
        );
    }
});
