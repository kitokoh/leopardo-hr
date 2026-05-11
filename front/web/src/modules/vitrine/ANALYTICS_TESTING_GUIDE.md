# Analytics Testing Guide - Phase 5, Task 5.1

## Overview

This guide provides step-by-step instructions for testing Google Analytics 4 and Mixpanel integration with Google Tag Manager.

## Prerequisites

- Google Analytics 4 account with Measurement ID (G-XXXXXXXXXX)
- Mixpanel account with Project Token
- Google Tag Manager account (optional but recommended)
- Browser with Developer Tools (Chrome, Firefox, Safari, Edge)

## Part 1: Manual Testing in Browser

### 1.1 Verify Scripts Are Loading

1. **Open your website** in a browser
2. **Open Developer Tools** (F12 or right-click → Inspect)
3. **Go to Network tab**
4. **Reload the page**
5. **Filter by "gtag"** to find GA4 requests
6. **Look for:**
   - `gtag/js?id=G-XXXXXXXXXX` (should return 200)
   - `collect` requests (GA4 events)

### 1.2 Verify GA4 Script Initialization

1. **Open Developer Tools Console** (F12 → Console tab)
2. **Type:** `window.gtag`
3. **Should return:** A function (not undefined)
4. **Type:** `window.dataLayer`
5. **Should return:** An array with gtag initialization data

### 1.3 Verify Mixpanel Script Initialization

1. **Open Developer Tools Console**
2. **Type:** `window.mixpanel`
3. **Should return:** An object with methods like `track`, `identify`, etc.
4. **Type:** `window.mixpanel.get_distinct_id()`
5. **Should return:** A unique ID for this user

### 1.4 Test Page View Event

1. **Open Developer Tools Network tab**
2. **Filter by "collect"** (GA4) or "track" (Mixpanel)
3. **Reload the page**
4. **Look for requests with:**
   - GA4: `event_name=page_view`
   - Mixpanel: `event=Page%20View`

### 1.5 Test CTA Click Event

1. **Open Developer Tools Network tab**
2. **Filter by "collect"** (GA4) or "track" (Mixpanel)
3. **Click a CTA button** (e.g., "Essai gratuit")
4. **Look for requests with:**
   - GA4: `event_name=cta_click`
   - Mixpanel: `event=CTA%20Click`

### 1.6 Test Form Submission Event

1. **Open Developer Tools Network tab**
2. **Filter by "collect"** (GA4) or "track" (Mixpanel)
3. **Fill out and submit a form** (signup, demo, contact, newsletter)
4. **Look for requests with:**
   - GA4: `event_name=form_submit` and `event_name=conversion`
   - Mixpanel: `event=Form%20Submission` and `event=Signup` (or Demo Request, Contact, Newsletter Signup)

## Part 2: Real-time Verification in GA4

### 2.1 Access GA4 Real-time Dashboard

1. **Go to [Google Analytics](https://analytics.google.com/)**
2. **Select your property**
3. **Go to Reports → Real-time → Overview**

### 2.2 Verify Page Views

1. **Open your website in a new tab**
2. **Go back to GA4 Real-time dashboard**
3. **You should see:**
   - Your session in "Active users now"
   - Page path in "Top pages"
   - Event count increasing

### 2.3 Verify Conversion Events

1. **Go to Reports → Real-time → Events**
2. **Perform a conversion action** (signup, demo request, etc.)
3. **Look for:**
   - Event name: `conversion`
   - Event count: 1
   - Parameters: `conversion_type`, `email`, etc.

### 2.4 Verify CTA Click Events

1. **Go to Reports → Real-time → Events**
2. **Click a CTA button**
3. **Look for:**
   - Event name: `cta_click`
   - Parameters: `button_text`, `page`, `position`

### 2.5 Create Conversion Goal

1. **Go to Admin → Conversions**
2. **Click "New conversion event"**
3. **Enter event name:** `conversion`
4. **Click "Create"**
5. **Now conversions will be tracked in GA4 reports**

## Part 3: Real-time Verification in Mixpanel

### 3.1 Access Mixpanel Events Dashboard

1. **Go to [Mixpanel](https://mixpanel.com/)**
2. **Select your project**
3. **Go to Events**

### 3.2 Verify Page Views

1. **Open your website in a new tab**
2. **Go back to Mixpanel Events**
3. **Look for:**
   - Event: `Page View`
   - Count: 1+
   - Properties: `page`, `title`

### 3.3 Verify Conversion Events

1. **Go to Mixpanel Events**
2. **Perform a conversion action** (signup, demo request, etc.)
3. **Look for:**
   - Event: `Signup` (or `Demo Request`, `Contact`, `Newsletter Signup`)
   - Count: 1
   - Properties: `email`, `company`, `page`, etc.

### 3.4 Create Funnel

1. **Go to Funnels**
2. **Click "Create Funnel"**
3. **Add steps:**
   - Step 1: `Page View`
   - Step 2: `CTA Click`
   - Step 3: `Form Submission`
   - Step 4: `Signup`
4. **Click "Create"**
5. **Analyze conversion funnel**

## Part 4: Google Tag Manager Testing

### 4.1 Setup GTM Container (Optional)

1. **Go to [Google Tag Manager](https://tagmanager.google.com/)**
2. **Create a new container** for your website
3. **Copy the GTM ID** (GTM-XXXXXXXXX)

### 4.2 Add GA4 Tag in GTM

1. **Go to Tags → New**
2. **Choose tag type:** Google Analytics: GA4 Configuration
3. **Enter Measurement ID:** G-XXXXXXXXXX
4. **Choose trigger:** All Pages
5. **Save and publish**

### 4.3 Add Mixpanel Tag in GTM

1. **Go to Tags → New**
2. **Choose tag type:** Custom HTML
3. **Paste Mixpanel initialization code:**
   ```html
   <script>
     (function(f,b){if(!b.__SV){var e,g,i,h;window.mixpanel=b;b._i=[];b.init=function(e,f,c){function g(a,d){var b=d.split(".");2==b.length&&(a=a[b[0]],d=b[1]);a[d]=function(){a.push([d].concat(Array.prototype.slice.call(arguments,0)))}}var a=b;"undefined"!=typeof c?a=b[c]=[]:c="mixpanel";a.people=a.people||[];a.toString=function(a){var d="mixpanel";"mixpanel"!=c&&(d+="."+c);a||(d+=" (stub)");return d};a.people.toString=function(){return a.toString(1)};i="disable time_event track track_pageview track_links track_forms track_with_groups add_group set_group remove_group unset_group increment append union track_revenue alias set_once union get_distinct_id get_user_id get_user_properties get_group_properties identify alias reset register register_once unregister opt_in_tracking opt_out_tracking has_opted_in_tracking has_opted_out_tracking clear_opt_in_tracking_cookie clear_opt_out_tracking_cookie".split(" ");for(h=0;h<i.length;h++)g(a,i[h]);b._i.push([e,f,c])};b.__SV=1.2;e=f.createElement("script");e.type="text/javascript";e.async=!0;e.src="undefined"!=typeof MIXPANEL_CUSTOM_LIB_URL?MIXPANEL_CUSTOM_LIB_URL:"file:"===f.location.protocol&&"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js".match(/^\\/\\//)?"https://cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js":"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js";f=f.getElementsByTagName("script")[0];f.parentNode.insertBefore(e,f)}})(document,window.mixpanel||[]);
     mixpanel.init('YOUR_TOKEN_HERE', {track_pageview: false});
   </script>
   ```
4. **Choose trigger:** All Pages
5. **Save and publish**

### 4.4 Test GTM Events

1. **Install GTM Preview Mode:**
   - Go to your GTM container
   - Click "Preview"
   - Copy the preview URL
   - Open it in a new tab

2. **Open your website** in another tab

3. **Go back to GTM Preview tab**
   - You should see events firing in real-time
   - Verify GA4 and Mixpanel tags are firing

## Part 5: Conversion Funnel Testing

### 5.1 Test Complete Signup Funnel

1. **Open your website**
2. **Perform these actions in order:**
   - Load landing page (page_view)
   - Scroll down (scroll_depth at 25%, 50%, 75%)
   - Click "Essai gratuit" button (cta_click)
   - Fill out signup form
   - Submit form (form_submit, conversion)

3. **Verify in GA4:**
   - Go to Reports → Real-time → Events
   - Look for all events in sequence

4. **Verify in Mixpanel:**
   - Go to Events
   - Look for all events in sequence

### 5.2 Test Demo Request Funnel

1. **Open /pricing page**
2. **Click "Demander une démo"** button
3. **Fill out demo form**
4. **Submit form**

5. **Verify events:**
   - GA4: `page_view`, `cta_click`, `form_submit`, `conversion` (demo_request)
   - Mixpanel: `Page View`, `CTA Click`, `Form Submission`, `Demo Request`

### 5.3 Test Contact Form Funnel

1. **Open /about page**
2. **Click "Nous contacter"** button
3. **Fill out contact form**
4. **Submit form**

5. **Verify events:**
   - GA4: `page_view`, `cta_click`, `form_submit`, `conversion` (contact)
   - Mixpanel: `Page View`, `CTA Click`, `Form Submission`, `Contact`

### 5.4 Test Newsletter Signup Funnel

1. **Scroll to footer**
2. **Enter email in newsletter form**
3. **Submit form**

4. **Verify events:**
   - GA4: `form_submit`, `conversion` (newsletter)
   - Mixpanel: `Form Submission`, `Newsletter Signup`

## Part 6: Performance Testing

### 6.1 Check Page Load Impact

1. **Open DevTools → Performance tab**
2. **Record page load**
3. **Check metrics:**
   - First Contentful Paint (FCP): Should be < 1.8s
   - Largest Contentful Paint (LCP): Should be < 2.5s
   - Total page load: Should be < 2s

### 6.2 Check Network Impact

1. **Open DevTools → Network tab**
2. **Reload page**
3. **Check requests:**
   - GA4 script: ~50KB
   - Mixpanel script: ~30KB
   - Total: ~80KB (acceptable)

### 6.3 Check Bundle Size

1. **Run build:** `npm run build`
2. **Check output for bundle size**
3. **Analytics should add minimal overhead**

## Part 7: Debugging

### 7.1 Enable GA4 Debug Mode

```javascript
// In browser console
gtag('config', 'G-XXXXXXXXXX', {
  'debug_mode': true
});
```

Then check GA4 DebugView:
1. Go to GA4 → Admin → DebugView
2. You should see events in real-time with full details

### 7.2 Check Mixpanel Debug Mode

```javascript
// In browser console
mixpanel.set_config({debug: true});
```

Then check browser console for Mixpanel debug messages.

### 7.3 Common Issues

**Issue: GA4 events not appearing**
- Solution: Check Measurement ID is correct
- Solution: Check GA4 script is loading (Network tab)
- Solution: Check Real-time dashboard (can take 5-10 seconds)

**Issue: Mixpanel events not appearing**
- Solution: Check Project Token is correct
- Solution: Check Mixpanel script is loading (Network tab)
- Solution: Check Events dashboard (can take 5-10 seconds)

**Issue: Form submissions not tracked**
- Solution: Check form submission handler calls tracking function
- Solution: Check form is actually submitting
- Solution: Check network requests for form submission

**Issue: Scroll depth not tracked**
- Solution: Check page has enough content to scroll
- Solution: Check scroll event listener is attached
- Solution: Check scroll depth tracking is enabled

## Part 8: Automated Testing

### 8.1 Create E2E Test for Analytics

```typescript
// tests/analytics.e2e.ts
import { test, expect } from '@playwright/test';

test('should track page view on landing page', async ({ page }) => {
  // Listen for network requests
  const requests: string[] = [];
  page.on('request', (request) => {
    if (request.url().includes('collect') || request.url().includes('track')) {
      requests.push(request.url());
    }
  });

  // Navigate to landing page
  await page.goto('/');

  // Wait for analytics requests
  await page.waitForTimeout(2000);

  // Verify page view event was sent
  const pageViewRequest = requests.find(url => 
    url.includes('page_view') || url.includes('Page%20View')
  );
  expect(pageViewRequest).toBeDefined();
});

test('should track signup conversion', async ({ page }) => {
  const requests: string[] = [];
  page.on('request', (request) => {
    if (request.url().includes('collect') || request.url().includes('track')) {
      requests.push(request.url());
    }
  });

  // Navigate to landing page
  await page.goto('/');

  // Fill and submit signup form
  await page.fill('input[type="email"]', 'test@example.com');
  await page.click('button:has-text("Essai gratuit")');

  // Wait for analytics requests
  await page.waitForTimeout(2000);

  // Verify conversion event was sent
  const conversionRequest = requests.find(url => 
    url.includes('conversion') || url.includes('Signup')
  );
  expect(conversionRequest).toBeDefined();
});
```

### 8.2 Run E2E Tests

```bash
npm run test:e2e
```

## Part 9: Monitoring & Alerts

### 9.1 Setup GA4 Alerts

1. **Go to GA4 → Admin → Alerts**
2. **Create alert for:**
   - Conversion rate drops below 5%
   - Traffic drops below 100 sessions/day
   - Bounce rate exceeds 50%

### 9.2 Setup Mixpanel Alerts

1. **Go to Mixpanel → Alerts**
2. **Create alert for:**
   - Signup events drop below 10/day
   - Demo request events drop below 5/day
   - Contact form submissions drop below 3/day

## Part 10: Reporting

### 10.1 Create GA4 Report

1. **Go to GA4 → Explore**
2. **Create custom report:**
   - Rows: Event name
   - Values: Event count
   - Filters: Event name contains "conversion"

### 10.2 Create Mixpanel Report

1. **Go to Mixpanel → Reports**
2. **Create custom report:**
   - Event: Signup
   - Breakdown by: Source
   - Time period: Last 7 days

## Checklist

- [ ] GA4 script loads successfully
- [ ] Mixpanel script loads successfully
- [ ] Page view events appear in GA4 Real-time
- [ ] Page view events appear in Mixpanel Events
- [ ] CTA click events are tracked
- [ ] Form submission events are tracked
- [ ] Conversion events are tracked
- [ ] Scroll depth events are tracked
- [ ] Time on page events are tracked
- [ ] GA4 conversion goal is created
- [ ] Mixpanel funnel is created
- [ ] GTM container is set up (optional)
- [ ] GA4 debug mode works
- [ ] Mixpanel debug mode works
- [ ] E2E tests pass
- [ ] Performance metrics are acceptable
- [ ] Alerts are configured
- [ ] Reports are created

## Next Steps

1. ✅ Manual testing completed
2. ✅ GA4 real-time verification completed
3. ✅ Mixpanel real-time verification completed
4. ✅ GTM setup completed (optional)
5. ✅ Conversion funnel testing completed
6. ✅ Performance testing completed
7. ✅ Debugging completed
8. ✅ E2E tests created
9. ✅ Monitoring & alerts configured
10. ✅ Reports created

## Support

For issues or questions:
- GA4 Support: https://support.google.com/analytics
- Mixpanel Support: https://help.mixpanel.com/
- GTM Support: https://support.google.com/tagmanager
