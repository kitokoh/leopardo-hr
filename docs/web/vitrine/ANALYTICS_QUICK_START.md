# Analytics Quick Start Guide

## 5-Minute Setup

### Step 1: Get Your IDs

**Google Analytics 4:**
1. Go to https://analytics.google.com/
2. Create a new property
3. Copy your Measurement ID (looks like: `G-XXXXXXXXXX`)

**Mixpanel:**
1. Go to https://mixpanel.com/
2. Create a new project
3. Copy your Project Token

### Step 2: Configure Environment

Add to `web/.env.local`:

```env
NEXT_PUBLIC_GA_ID=G-XXXXXXXXXX
NEXT_PUBLIC_MIXPANEL_TOKEN=your_token_here
```

### Step 3: Verify Installation

1. Start your dev server: `npm run dev`
2. Open http://localhost:3000
3. Open DevTools (F12)
4. Go to Network tab
5. Look for requests to `collect` (GA4) or `track` (Mixpanel)
6. You should see events being sent!

---

## Using Analytics in Your Components

### Track Page Views

```typescript
'use client';

import { useAnalyticsPage } from '@/modules/vitrine/hooks';

export default function MyPage() {
  useAnalyticsPage('/my-page', 'My Page Title');
  
  return <div>Page content</div>;
}
```

### Track Form Submissions

```typescript
'use client';

import { useAnalyticsForm } from '@/modules/vitrine/hooks';

export function SignupForm() {
  const { trackSignup } = useAnalyticsForm();

  const handleSubmit = (email: string) => {
    trackSignup(email);
    // Submit form...
  };

  return (
    <form onSubmit={(e) => {
      e.preventDefault();
      handleSubmit(e.currentTarget.email.value);
    }}>
      <input type="email" name="email" required />
      <button type="submit">Sign Up</button>
    </form>
  );
}
```

### Track CTA Clicks

```typescript
'use client';

import { useAnalyticsCTA } from '@/modules/vitrine/hooks';

export function CTAButton() {
  const { trackCTAClick } = useAnalyticsCTA();

  return (
    <button onClick={() => trackCTAClick('Essai gratuit', 'hero')}>
      Essai gratuit
    </button>
  );
}
```

---

## Verify in Dashboards

### Google Analytics 4

1. Go to https://analytics.google.com/
2. Select your property
3. Go to Reports → Real-time → Overview
4. You should see your session and events!

### Mixpanel

1. Go to https://mixpanel.com/
2. Select your project
3. Go to Events
4. You should see events appearing in real-time!

---

## Common Events

| Event | When | GA4 | Mixpanel |
|-------|------|-----|----------|
| Page View | Page loads | `page_view` | `Page View` |
| Signup | User signs up | `conversion` | `Signup` |
| Demo Request | User requests demo | `conversion` | `Demo Request` |
| Contact | User submits contact form | `conversion` | `Contact` |
| Newsletter | User subscribes to newsletter | `conversion` | `Newsletter Signup` |
| CTA Click | User clicks CTA button | `cta_click` | `CTA Click` |
| Form Submit | User submits any form | `form_submit` | `Form Submission` |
| Scroll Depth | User scrolls to 25%, 50%, 75%, 100% | `scroll_depth` | `Scroll Depth` |
| Time on Page | User spends time on page | `time_on_page` | `Time on Page` |

---

## Troubleshooting

### Events not appearing?

1. **Check environment variables:**
   ```bash
   echo $NEXT_PUBLIC_GA_ID
   echo $NEXT_PUBLIC_MIXPANEL_TOKEN
   ```

2. **Check Network tab:**
   - Open DevTools → Network
   - Look for `collect` (GA4) or `track` (Mixpanel)
   - Should see requests being sent

3. **Check Real-time dashboards:**
   - GA4: Real-time can take 5-10 seconds
   - Mixpanel: Events appear within seconds

4. **Check browser console:**
   - Look for any error messages
   - Type `window.gtag` (should be a function)
   - Type `window.mixpanel` (should be an object)

---

## Next Steps

1. ✅ Setup GA4 and Mixpanel
2. ✅ Configure environment variables
3. ✅ Verify events in dashboards
4. → Integrate tracking in your components
5. → Create conversion goals in GA4
6. → Create funnels in Mixpanel
7. → Monitor and optimize

---

## Full Documentation

- [Implementation Guide](./ANALYTICS_IMPLEMENTATION.md)
- [Testing Guide](./ANALYTICS_TESTING_GUIDE.md)
- [Complete Summary](./PHASE5_TASK5_1_SUMMARY.md)

---

## Support

- GA4: https://support.google.com/analytics
- Mixpanel: https://help.mixpanel.com/
- Next.js: https://nextjs.org/docs
