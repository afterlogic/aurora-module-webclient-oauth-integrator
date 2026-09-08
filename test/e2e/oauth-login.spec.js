const path = require('path')
const { sharedHelper } = require(path.join(
  process.env.AURORA_E2E_ROOT,
  'helpers/paths'
))
const { test, expect } = require('@playwright/test')
const { T } = sharedHelper('timeouts')
const { step, attachScreenshot } = sharedHelper('login')

function oauthButtons(page) {
  return page
    .getByTestId('oauth-signin-button')
    .or(page.locator('.content.social .button'))
}

test.describe('Desktop OAuth social login', () => {
  test('shows social buttons and starts OAuth flow', async ({
    page,
    browserName,
    baseURL,
  }) => {
    test.setTimeout(T(120000))
    test.skip(browserName !== 'chromium', 'Chrome-only new P3 spec')

    await step('Open login page', async () => {
      await page.context().clearCookies()
      await page.goto('', { waitUntil: 'domcontentloaded', timeout: T(60000) })
      await expect(page.getByTestId('login-email')).toBeVisible({
        timeout: T(30000),
      })
    })

    const buttons = oauthButtons(page)
    test.skip(
      (await buttons.count()) === 0,
      'OAuth social login buttons are not available on this stand'
    )

    await step('Inspect available OAuth providers', async () => {
      const count = await buttons.count()
      console.log(`  → OAuth buttons: ${count}`)
      await expect(buttons.first()).toBeVisible({ timeout: T(15000) })
      await attachScreenshot(page, 'oauth-login-01-buttons')
    })

    await step('Click first provider and confirm OAuth bootstrap', async () => {
      const first = buttons.first()
      const service =
        (await first.getAttribute('data-oauth-service').catch(() => '')) ||
        ((await first.innerText().catch(() => '')).trim().split(/\s+/).pop() || '')
      const serviceSlug = service.toLowerCase()

      test.skip(!serviceSlug, 'Cannot determine OAuth provider name from the first button')

      await page.route(
        new RegExp(`/\\?oauth=${serviceSlug}(?:$|&)`),
        async (route) => {
          await route.fulfill({
            status: 200,
            contentType: 'text/html',
            body: '<html><body>oauth intercepted</body></html>',
          })
        },
        { times: 1 }
      )

      await Promise.all([
        page.waitForURL(new RegExp(`\\?oauth=${serviceSlug}(?:$|&)`), {
          timeout: T(30000),
        }),
        first.click(),
      ])

      const cookies = await page.context().cookies(baseURL ? [baseURL] : undefined)
      const redirectCookie = cookies.find((c) => c.name === 'oauth-redirect')
      const scopesCookie = cookies.find((c) => c.name === 'oauth-scopes')

      expect(redirectCookie?.value).toBe('login')
      expect(scopesCookie?.value).toBe('auth')
      await attachScreenshot(page, 'oauth-login-02-started')
    })
  })
})
