import { chromium } from 'playwright';
import dns from 'node:dns/promises';
import net from 'node:net';

const [url] = process.argv.slice(2);

if (!url) {
  throw new Error('A URL is required.');
}

const target = new URL(url);
const originHost = target.hostname.toLowerCase();
const trustedHostSuffix = originHost.startsWith('www.') ? originHost.slice(4) : originHost;

function isPublicIpv4(ip) {
  const octets = ip.split('.').map(Number);
  if (octets.length !== 4 || octets.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) return false;
  const [a, b] = octets;
  return a !== 0
    && a !== 10
    && a !== 127
    && !(a === 100 && b >= 64 && b <= 127)
    && !(a === 169 && b === 254)
    && !(a === 172 && b >= 16 && b <= 31)
    && !(a === 192 && (b === 0 || b === 168))
    && !(a === 198 && (b === 18 || b === 19 || b === 51))
    && !(a === 203 && b === 0)
    && a < 224;
}

async function isSafeFirstPartyUrl(rawUrl) {
  let candidate;
  try {
    candidate = new URL(rawUrl);
  } catch {
    return false;
  }

  const hostname = candidate.hostname.toLowerCase();
  if (!['http:', 'https:'].includes(candidate.protocol)
    || (hostname !== trustedHostSuffix && !hostname.endsWith(`.${trustedHostSuffix}`))) return false;

  if (net.isIP(hostname) === 4) return isPublicIpv4(hostname);

  try {
    const records = await dns.resolve4(hostname);
    return records.some(isPublicIpv4);
  } catch {
    return false;
  }
}

const browser = await chromium.launch({ headless: true });
try {
  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (compatible; AI-Plus-LSTS-WebReader/1.0; +https://lsts.edu.vn)',
    locale: 'vi-VN',
  });
  const page = await context.newPage();

  // A page can execute arbitrary JavaScript. Let it load only public hosts in
  // the site's own domain (including its API subdomains) and reject external
  // assets, redirects, or fetches aimed at another organisation.
  await page.route('**/*', async (route) => {
    if (await isSafeFirstPartyUrl(route.request().url())) {
      await route.continue();
    } else {
      await route.abort();
    }
  });

  const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15_000 });
  await page.waitForTimeout(1_500);
  const text = await page.locator('body').innerText({ timeout: 5_000 });

  process.stdout.write(JSON.stringify({
    ok: response?.ok() ?? false,
    status: response?.status() ?? 0,
    finalUrl: page.url(),
    text,
  }));
} finally {
  await browser.close();
}
