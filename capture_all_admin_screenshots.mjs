import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

const SCREENSHOT_DIR = 'E:/wamp64/www/naoero-airlines/docs/screenshots';
if (!fs.existsSync(SCREENSHOT_DIR)) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function captureAll() {
  console.log('Launching browser...');
  const browser = await puppeteer.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,900']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1.5 });

  const BASE_URL = 'http://127.0.0.1:8000';

  // 1. Capture Login Portal
  console.log('1. Capturing 01_login_portal.png...');
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  await new Promise(r => setTimeout(r, 600));
  await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_login_portal.png') });

  // 2. Perform Login
  console.log('Logging in as Super Admin...');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.evaluate(() => {
      document.getElementById('email').value = 'admin@naoero-airlines.com';
      document.getElementById('password').value = 'password';
      document.querySelector('form').submit();
    })
  ]);

  console.log('Logged in successfully! Current URL:', page.url());

  const pages = [
    { file: '02_admin_dashboard.png', url: '/admin/dashboard' },
    { file: '03_destinations_directory.png', url: '/admin/destinations' },
    { file: '04_destination_editor.png', url: '/admin/destinations/1/edit' },
    { file: '05_fleet_aircraft.png', url: '/admin/fleets' },
    { file: '06_inquiries_inbox.png', url: '/admin/inquiries' },
    { file: '07_inquiry_detail_view.png', url: '/admin/inquiries/1' },
    { file: '08_flight_schedules.png', url: '/admin/schedules' },
    { file: '09_careers_hr.png', url: '/admin/careers' },
    { file: '10_job_applications.png', url: '/admin/job-applications' },
    { file: '11_travel_agencies.png', url: '/admin/agencies' },
    { file: '12_news_blog_hub.png', url: '/admin/posts' },
    { file: '13_special_deals.png', url: '/admin/specials' },
    { file: '14_media_gallery.png', url: '/admin/gallery' },
    { file: '15_faqs_management.png', url: '/admin/faqs' },
    { file: '16_testimonials.png', url: '/admin/testimonials' },
    { file: '17_team_management.png', url: '/admin/team-members' },
    { file: '18_user_management.png', url: '/admin/users' },
    { file: '19_rbac_permission_matrix.png', url: '/admin/roles/matrix' },
    { file: '20_emergency_notice_settings.png', url: '/admin/settings' },
    { file: '21_activity_audit_logs.png', url: '/admin/activity-logs' },
    { file: '22_system_cache_optimizer.png', url: '/admin/system' },
  ];

  for (const p of pages) {
    try {
      console.log(`Capturing ${p.file} from ${p.url}...`);
      await page.goto(`${BASE_URL}${p.url}`, { waitUntil: 'domcontentloaded' });
      await new Promise(r => setTimeout(r, 400));
      await page.screenshot({ path: path.join(SCREENSHOT_DIR, p.file) });
    } catch (e) {
      console.error(`Error capturing ${p.file}:`, e.message);
    }
  }

  await browser.close();
  console.log('All 22 Admin Panel screenshots captured with full fidelity!');
}

captureAll().catch(console.error);
