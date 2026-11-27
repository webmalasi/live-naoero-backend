import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

const SCREENSHOT_DIR = 'E:/wamp64/www/naoero-airlines/docs/screenshots';
if (!fs.existsSync(SCREENSHOT_DIR)) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function captureScreenshots() {
  console.log('Starting browser for screenshot capture...');
  const browser = await puppeteer.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,900']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1.5 });

  const BASE_URL = 'http://127.0.0.1:8000';

  // 1. Login Page
  console.log('Capturing 01_login_page.png...');
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  await new Promise(r => setTimeout(r, 800));
  await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_login_page.png') });

  // Perform Login
  console.log('Logging in as Super Admin...');
  await page.type('#email', 'admin@naoero-airlines.com');
  await page.type('#password', 'password');
  
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(e => console.log('Navigation:', e.message)),
    page.click('button[type="submit"]')
  ]);

  console.log('Current URL after login:', page.url());

  const pagesToCapture = [
    { name: '02_dashboard.png', url: '/admin/dashboard' },
    { name: '03_destinations_list.png', url: '/admin/destinations' },
    { name: '04_destination_edit.png', url: '/admin/destinations/1/edit' },
    { name: '05_fleets.png', url: '/admin/fleets' },
    { name: '06_inquiries_inbox.png', url: '/admin/inquiries' },
    { name: '07_inquiry_detail.png', url: '/admin/inquiries/1' },
    { name: '08_flight_schedules.png', url: '/admin/schedules' },
    { name: '09_careers.png', url: '/admin/careers' },
    { name: '10_job_applications.png', url: '/admin/job-applications' },
    { name: '11_travel_agencies.png', url: '/admin/agencies' },
    { name: '12_posts_news.png', url: '/admin/posts' },
    { name: '13_special_offers.png', url: '/admin/specials' },
    { name: '14_gallery.png', url: '/admin/gallery' },
    { name: '15_faqs.png', url: '/admin/faqs' },
    { name: '16_testimonials.png', url: '/admin/testimonials' },
    { name: '17_team_members.png', url: '/admin/team-members' },
    { name: '18_users_management.png', url: '/admin/users' },
    { name: '19_role_permission_matrix.png', url: '/admin/roles/matrix' },
    { name: '20_site_settings_notice.png', url: '/admin/settings' },
    { name: '21_activity_logs.png', url: '/admin/activity-logs' },
    { name: '22_system_optimizer.png', url: '/admin/system' },
  ];

  for (const item of pagesToCapture) {
    try {
      console.log(`Capturing ${item.name} from ${item.url}...`);
      await page.goto(`${BASE_URL}${item.url}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
      await new Promise(r => setTimeout(r, 600));
      await page.screenshot({ path: path.join(SCREENSHOT_DIR, item.name) });
    } catch (err) {
      console.error(`Error capturing ${item.name}:`, err.message);
    }
  }

  await browser.close();
  console.log('All screenshots captured successfully!');
}

captureScreenshots().catch(console.error);
