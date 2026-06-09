import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8920';
const OUT = '/tmp/shots';

const publicPages = [
    ['01-login', '/login'],
    ['02-register', '/register'],
    ['03-forgot-password', '/forgot-password'],
];

const appPages = [
    ['04-inbox', '/inbox'],
    ['05-dashboard', '/dashboard'],
    ['06-contacts', '/contacts'],
    ['07-contact-profile', '/contacts/1'],
    ['08-chatbots', '/chatbots'],
    ['09-flow-builder', '/chatbots/1/edit'],
    ['10-broadcasts', '/broadcasts'],
    ['11-broadcast-create', '/broadcasts/create'],
    ['12-templates', '/templates'],
    ['13-automations', '/automations'],
    ['14-orders', '/orders'],
    ['15-products', '/products'],
    ['16-settings-workspace', '/settings'],
    ['17-settings-team', '/settings/team'],
    ['18-settings-channels', '/settings/channels'],
    ['19-settings-hours', '/settings/hours'],
    ['20-settings-content', '/settings/content'],
    ['21-settings-widget', '/settings/widget'],
    ['22-settings-qr', '/settings/qr'],
    ['23-settings-billing', '/settings/billing'],
    ['24-settings-wallet', '/settings/wallet'],
    ['25-settings-developer', '/settings/developer'],
    ['26-settings-profile', '/settings/profile'],
    ['27-onboarding', '/onboarding'],
];

const shoot = async (page, name) => {
    await page.waitForTimeout(700); // let fonts/animation settle
    await page.screenshot({ path: `${OUT}/${name}.png` });
    console.log('shot', name);
};

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();

// Public pages (light)
for (const [name, path] of publicPages) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await shoot(page, name);
}

// Log in
await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'demo@myalice.test');
await page.fill('input[type="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL('**/inbox', { timeout: 15000 });

// App pages (light)
for (const [name, path] of appPages) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await shoot(page, name);
}

// Dark-mode inbox
await page.evaluate(() => {
    localStorage.setItem('myalice-theme', 'dark');
    document.documentElement.classList.add('dark');
});
await page.goto(BASE + '/inbox', { waitUntil: 'networkidle' });
await shoot(page, '28-inbox-dark');
await page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
await shoot(page, '29-dashboard-dark');

// Back to light, Arabic RTL inbox
await page.evaluate(() => {
    localStorage.setItem('myalice-theme', 'light');
    document.documentElement.classList.remove('dark');
});
await page.request.post(BASE + '/locale', {
    form: { locale: 'ar' },
    headers: { 'X-XSRF-TOKEN': decodeURIComponent((await ctx.cookies()).find((c) => c.name === 'XSRF-TOKEN')?.value ?? '') },
});
await page.goto(BASE + '/inbox', { waitUntil: 'networkidle' });
await shoot(page, '30-inbox-arabic-rtl');

await browser.close();
console.log('DONE');
