import asyncio
import argparse
import os
from playwright.async_api import async_playwright

# Configuration
CREDENTIALS = {
    'email': 'admin@leopardo-rh.com',
    'password': 'password123'
}

ROUTES = {
    'web_showcase': [
        '/', '/about', '/blog', '/branding', '/careers', '/case-studies',
        '/changelog', '/checkout', '/comptabilite', '/contact', '/demo',
        '/docs', '/documents', '/download', '/employes', '/faq', '/guides',
        '/integrations', '/marketing', '/mobile', '/pricing', '/signup',
        '/testimonials', '/videos'
    ],
    'web_dashboard': [
        '/dashboard', '/employees', '/absences', '/attendance', '/contracts',
        '/partner', '/payroll', '/reports', '/settings', '/settings/developer',
        '/settings/notifications', '/training'
    ],
    'admin': [
        '/login', '/', '/analytics', '/globe', '/users', '/companies',
        '/subscriptions', '/support', '/crm/pipeline', '/system', '/logs',
        '/payroll', '/leaves', '/contracts', '/recruitment', '/training',
        '/fleet', '/chat', '/webhooks', '/exports', '/reports', '/predictions',
        '/audit', '/growth'
    ],
    'mobile_employee': [
        '/welcome', '/login', '/register', '/', '/modules', '/absences',
        '/salary-advances', '/payrolls', '/notifications', '/evaluations',
        '/attendance', '/history', '/me/monthly', '/cabinet', '/settings',
        '/user-register', '/user-login', '/user-home', '/company-request',
        '/contracts', '/training', '/expenses', '/ai-chat', '/ai-voice',
        '/vehicle-map', '/onboarding'
    ],
    'mobile_manager': [
        '/welcome', '/login', '/register', '/', '/modules', '/absences',
        '/salary-advances', '/payrolls', '/notifications', '/evaluations',
        '/attendance', '/history', '/me/monthly', '/team', '/tasks',
        '/modules/rh', '/cabinet', '/settings', '/user-register', '/user-login',
        '/user-home', '/company-request', '/contracts', '/training', '/expenses',
        '/ai-chat', '/ai-voice', '/vehicle-map', '/approvals', '/onboarding',
        '/organigramme', '/schedules', '/company/branding', '/manager/dashboard',
        '/manager/attendance', '/manager/anomalies', '/manager/corrections'
    ],
    'mobile_platform_admin': [
        '/platform/login', '/platform', '/platform/companies',
        '/platform/companies/new', '/platform/company-requests'
    ]
}

async def authenticate_web(page, base_url):
    print(f"Bypassing Web authentication at {base_url}...")
    await page.goto(base_url)
    await page.evaluate("""
        localStorage.setItem('auth_token', 'mock-token');
        localStorage.setItem('leopardo_auth_user', JSON.stringify({
            id: 1,
            email: 'admin@leopardo-rh.com',
            name: 'Ahmed Benali',
            role: 'manager',
            manager_role: 'principal',
            company: { id: 1, name: 'TechCorp Algerie', metadata: { onboarding_completed: true } }
        }));
    """)
    print("Web authentication bypassed with mock data.")

async def authenticate_admin(page, base_url):
    print(f"Bypassing Admin authentication at {base_url}...")
    await page.goto(base_url)
    await page.evaluate("""
        localStorage.setItem('admin_token', 'mock-admin-token');
        localStorage.setItem('admin_user', JSON.stringify({
            id: 1,
            email: 'admin@leopardo-rh.com',
            name: 'Platform Admin',
            role: 'super_admin'
        }));
    """)
    print("Admin authentication bypassed with mock data.")

async def authenticate_mobile(page, base_url, login_path='/login'):
    print(f"Authenticating Mobile at {base_url}{login_path}...")
    await page.goto(f"{base_url}#{login_path}")
    # Flutter web usually uses canvaskit or html. Inputs might be tricky.
    # We'll try to find by placeholder or label.
    await asyncio.sleep(5) # Wait for Flutter to load
    try:
        await page.get_by_placeholder("Email").fill(CREDENTIALS['email'])
        await page.get_by_placeholder("Mot de passe").fill(CREDENTIALS['password'])
        await page.get_by_role("button", name="Se connecter").click()
        await asyncio.sleep(5)
    except Exception as e:
        print(f"Mobile authentication failed or already authenticated: {e}")

async def capture_screenshots(platform):
    async with async_playwright() as p:
        browser = await p.chromium.launch()

        if platform.startswith('mobile'):
            # Mobile emulation
            context = await browser.new_context(
                viewport={'width': 390, 'height': 844},
                user_agent='Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1'
            )
            base_url = "http://localhost:8080"
        elif platform == 'admin':
            context = await browser.new_context(viewport={'width': 1280, 'height': 800})
            base_url = "http://localhost:3002"
        else:
            context = await browser.new_context(viewport={'width': 1280, 'height': 800})
            base_url = "http://localhost:3001"

        page = await context.new_page()

        # Handle Auth
        if platform == 'web_dashboard':
            await authenticate_web(page, base_url)
        elif platform == 'admin':
            await authenticate_admin(page, base_url)
        elif platform == 'mobile_employee' or platform == 'mobile_manager':
            await authenticate_mobile(page, base_url)
        elif platform == 'mobile_platform_admin':
            await authenticate_mobile(page, base_url, login_path='/platform/login')

        routes = ROUTES[platform]
        for route in routes:
            url = f"{base_url}{route}" if not platform.startswith('mobile') else f"{base_url}#{route}"
            print(f"Capturing {url}...")
            try:
                await page.goto(url, wait_until='networkidle')
                await asyncio.sleep(2) # Extra wait for animations
                filename = route.replace('/', '_').strip('_') or 'index'
                path = f"screenshots/{platform}/{filename}.png"
                await page.screenshot(path=path, full_page=True)
                print(f"Saved to {path}")
            except Exception as e:
                print(f"Failed to capture {url}: {e}")

        await browser.close()

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument('--platform', required=True, choices=ROUTES.keys())
    args = parser.parse_args()

    asyncio.run(capture_screenshots(args.platform))
