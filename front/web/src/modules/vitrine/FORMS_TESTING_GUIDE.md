# Forms Testing Guide

## Overview

This guide provides comprehensive testing procedures for all form components and API routes.

---

## Manual Testing

### 1. SignupForm Testing

#### Test 1.1: Valid Signup
1. Navigate to signup page
2. Enter valid email: `test@example.com`
3. Enter password: `SecurePass123!`
4. Confirm password: `SecurePass123!`
5. Check "Accept terms"
6. Click "Créer mon compte"
7. **Expected:** Success message appears, form clears

#### Test 1.2: Invalid Email
1. Enter email: `invalid-email`
2. Click outside email field
3. **Expected:** Error message "Email invalide"

#### Test 1.3: Weak Password
1. Enter password: `weak`
2. Click outside password field
3. **Expected:** Error message about password requirements

#### Test 1.4: Password Mismatch
1. Enter password: `SecurePass123!`
2. Enter confirm password: `DifferentPass123!`
3. Click submit
4. **Expected:** Error message "Les mots de passe ne correspondent pas"

#### Test 1.5: Terms Not Accepted
1. Fill all fields correctly
2. Don't check "Accept terms"
3. Click submit
4. **Expected:** Error message about accepting terms

#### Test 1.6: Show/Hide Password
1. Enter password
2. Click password visibility toggle
3. **Expected:** Password becomes visible
4. Click toggle again
5. **Expected:** Password becomes hidden

#### Test 1.7: Loading State
1. Fill form correctly
2. Click submit
3. **Expected:** Button shows loading spinner
4. **Expected:** Button is disabled during submission

#### Test 1.8: Dark Mode
1. Toggle dark mode
2. **Expected:** Form colors adjust for dark mode
3. **Expected:** Text is readable
4. **Expected:** Inputs have proper contrast

#### Test 1.9: Mobile Responsive
1. Resize browser to 320px width
2. **Expected:** Form is single column
3. **Expected:** All fields are readable
4. **Expected:** Button is full width

#### Test 1.10: Analytics Tracking
1. Open browser DevTools
2. Go to Network tab
3. Fill and submit form
4. **Expected:** GA4 event sent
5. **Expected:** Mixpanel event sent

---

### 2. DemoForm Testing

#### Test 2.1: Valid Demo Request
1. Navigate to demo page
2. Enter name: `Jean Dupont`
3. Enter email: `jean@example.com`
4. Enter company: `Acme Corp`
5. Select employees: `51-200`
6. Select date: Any future weekday
7. Click "Demander une démo"
8. **Expected:** Success message appears

#### Test 2.2: Calendar Date Picker
1. Click date field
2. **Expected:** Calendar shows next 30 days
3. **Expected:** Weekends are excluded
4. **Expected:** Past dates are not available
5. Select a date
6. **Expected:** Date appears in field

#### Test 2.3: Optional Fields
1. Fill required fields only (name, email, company)
2. Leave phone and date empty
3. Click submit
4. **Expected:** Form submits successfully

#### Test 2.4: Phone Validation
1. Enter invalid phone: `abc123`
2. Click outside field
3. **Expected:** Error message about phone format

#### Test 2.5: Company Name Validation
1. Enter company: `A` (too short)
2. Click outside field
3. **Expected:** Error message about minimum length

#### Test 2.6: Employee Count Selection
1. Click employee count dropdown
2. **Expected:** All options visible
3. Select `11-50`
4. **Expected:** Selection is saved

#### Test 2.7: Rate Limiting
1. Submit form 5 times rapidly
2. **Expected:** 5th submission succeeds
3. Try 6th submission
4. **Expected:** Error "Trop de tentatives"

#### Test 2.8: Success Message
1. Submit valid form
2. **Expected:** Success message shows
3. **Expected:** Message includes "Nous vous contacterons bientôt"
4. Wait 5 seconds
5. **Expected:** Message disappears

#### Test 2.9: Form Reset
1. Fill form with data
2. Submit successfully
3. **Expected:** Form fields are cleared
4. **Expected:** Form is ready for new submission

#### Test 2.10: Mobile Responsive
1. Resize to mobile (320px)
2. **Expected:** All fields stack vertically
3. **Expected:** Calendar is usable on mobile
4. **Expected:** Buttons are full width

---

### 3. ContactForm Testing

#### Test 3.1: Valid Contact Message
1. Navigate to contact page
2. Enter name: `Jean Dupont`
3. Enter email: `jean@example.com`
4. Enter subject: `Question about pricing`
5. Enter message: `I would like to know more about your pricing plans...`
6. Click "Envoyer le message"
7. **Expected:** Success message appears

#### Test 3.2: Message Validation
1. Enter message: `Short` (too short)
2. Click outside field
3. **Expected:** Error message about minimum length

#### Test 3.3: Subject Validation
1. Enter subject: `Hi` (too short)
2. Click outside field
3. **Expected:** Error message about minimum length

#### Test 3.4: Textarea Auto-resize
1. Click message field
2. Type multiple lines
3. **Expected:** Textarea expands automatically
4. **Expected:** No scrollbar appears

#### Test 3.5: Optional Phone Field
1. Fill required fields only
2. Leave phone empty
3. Click submit
4. **Expected:** Form submits successfully

#### Test 3.6: Phone Validation
1. Enter invalid phone: `123`
2. Click outside field
3. **Expected:** Error message about phone format

#### Test 3.7: Error Message Display
1. Enter invalid email: `invalid`
2. Click outside field
3. **Expected:** Error message appears inline
4. **Expected:** Error message is red
5. **Expected:** Error icon appears

#### Test 3.8: Success Message
1. Submit valid form
2. **Expected:** Success message shows
3. **Expected:** Message includes "Nous vous répondrons bientôt"

#### Test 3.9: Form Persistence
1. Fill form partially
2. Refresh page
3. **Expected:** Form is cleared (no persistence)

#### Test 3.10: Accessibility
1. Tab through form fields
2. **Expected:** All fields are reachable
3. **Expected:** Focus indicator is visible
4. **Expected:** Labels are associated with fields

---

### 4. NewsletterForm Testing

#### Test 4.1: Default Variant
1. Render NewsletterForm with variant="default"
2. **Expected:** Card layout appears
3. **Expected:** Title and description visible
4. **Expected:** Email input and button visible

#### Test 4.2: Compact Variant
1. Render NewsletterForm with variant="compact"
2. **Expected:** Inline form appears
3. **Expected:** Email input and button on same line
4. **Expected:** Minimal styling

#### Test 4.3: Inline Variant
1. Render NewsletterForm with variant="inline"
2. **Expected:** Horizontal layout appears
3. **Expected:** Title and description on left
4. **Expected:** Form on right (desktop)

#### Test 4.4: Valid Subscription
1. Enter email: `user@example.com`
2. Click "S'inscrire"
3. **Expected:** Success message appears
4. **Expected:** Email is cleared

#### Test 4.5: Invalid Email
1. Enter email: `invalid`
2. Click "S'inscrire"
3. **Expected:** Error message appears

#### Test 4.6: Duplicate Email
1. Subscribe with email: `user@example.com`
2. Try to subscribe again with same email
3. **Expected:** Appropriate response (success or error)

#### Test 4.7: Rate Limiting
1. Submit 10 times rapidly
2. **Expected:** 10th submission succeeds
3. Try 11th submission
4. **Expected:** Error "Trop de tentatives"

#### Test 4.8: Custom Title/Description
1. Render with custom title and description
2. **Expected:** Custom text appears
3. **Expected:** Text is properly formatted

#### Test 4.9: Loading State
1. Click submit
2. **Expected:** Button shows loading spinner
3. **Expected:** Button is disabled

#### Test 4.10: Mobile Responsive
1. Resize to mobile (320px)
2. **Expected:** Form is readable
3. **Expected:** Button is full width
4. **Expected:** Layout adapts to screen size

---

## API Testing

### 1. Signup API Testing

#### Test 1.1: Valid Request
```bash
curl -X POST http://localhost:3000/api/forms/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!",
    "page": "/signup"
  }'
```
**Expected Response:**
```json
{
  "success": true,
  "message": "Inscription réussie! Vérifiez votre email.",
  "data": {
    "email": "test@example.com",
    "confirmationSent": true
  }
}
```

#### Test 1.2: Invalid Email
```bash
curl -X POST http://localhost:3000/api/forms/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "invalid",
    "password": "SecurePass123!"
  }'
```
**Expected Response:** 400 Bad Request

#### Test 1.3: Weak Password
```bash
curl -X POST http://localhost:3000/api/forms/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "weak"
  }'
```
**Expected Response:** 400 Bad Request

#### Test 1.4: Rate Limiting
```bash
# Submit 5 times
for i in {1..5}; do
  curl -X POST http://localhost:3000/api/forms/signup \
    -H "Content-Type: application/json" \
    -d '{"email": "test@example.com", "password": "SecurePass123!"}'
done

# 6th attempt should fail
curl -X POST http://localhost:3000/api/forms/signup \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "SecurePass123!"}'
```
**Expected Response:** 429 Too Many Requests

---

### 2. Demo API Testing

#### Test 2.1: Valid Request
```bash
curl -X POST http://localhost:3000/api/forms/demo \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Dupont",
    "email": "jean@example.com",
    "company": "Acme Corp",
    "employees": "51-200",
    "page": "/demo"
  }'
```
**Expected Response:**
```json
{
  "success": true,
  "message": "Demande de démo envoyée! Nous vous contacterons bientôt.",
  "data": {
    "email": "jean@example.com",
    "name": "Jean Dupont",
    "company": "Acme Corp",
    "confirmationSent": true
  }
}
```

#### Test 2.2: Missing Required Field
```bash
curl -X POST http://localhost:3000/api/forms/demo \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Dupont",
    "email": "jean@example.com"
  }'
```
**Expected Response:** 400 Bad Request

#### Test 2.3: Invalid Date
```bash
curl -X POST http://localhost:3000/api/forms/demo \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Dupont",
    "email": "jean@example.com",
    "company": "Acme Corp",
    "preferredDate": "2020-01-01"
  }'
```
**Expected Response:** 400 Bad Request (past date)

---

### 3. Contact API Testing

#### Test 3.1: Valid Request
```bash
curl -X POST http://localhost:3000/api/forms/contact \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Dupont",
    "email": "jean@example.com",
    "subject": "Question about pricing",
    "message": "I would like to know more about your pricing plans...",
    "page": "/contact"
  }'
```
**Expected Response:**
```json
{
  "success": true,
  "message": "Message envoyé! Nous vous répondrons bientôt.",
  "data": {
    "email": "jean@example.com",
    "name": "Jean Dupont",
    "subject": "Question about pricing",
    "confirmationSent": true
  }
}
```

#### Test 3.2: Message Too Short
```bash
curl -X POST http://localhost:3000/api/forms/contact \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Dupont",
    "email": "jean@example.com",
    "subject": "Question",
    "message": "Short"
  }'
```
**Expected Response:** 400 Bad Request

---

### 4. Newsletter API Testing

#### Test 4.1: Valid Request
```bash
curl -X POST http://localhost:3000/api/forms/newsletter \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "page": "/newsletter"
  }'
```
**Expected Response:**
```json
{
  "success": true,
  "message": "Inscription à la newsletter réussie!",
  "data": {
    "email": "user@example.com",
    "confirmationSent": true
  }
}
```

#### Test 4.2: Invalid Email
```bash
curl -X POST http://localhost:3000/api/forms/newsletter \
  -H "Content-Type: application/json" \
  -d '{
    "email": "invalid"
  }'
```
**Expected Response:** 400 Bad Request

---

## Analytics Testing

### 1. GA4 Event Tracking

#### Test 1.1: Signup Event
1. Open DevTools → Network tab
2. Filter by "collect"
3. Submit signup form
4. **Expected:** GA4 event sent with:
   - `conversion_type: signup`
   - `email: user@example.com`
   - `page: /signup`

#### Test 1.2: Demo Request Event
1. Open DevTools → Network tab
2. Filter by "collect"
3. Submit demo form
4. **Expected:** GA4 event sent with:
   - `conversion_type: demo_request`
   - `email: jean@example.com`
   - `company: Acme Corp`

#### Test 1.3: Contact Event
1. Open DevTools → Network tab
2. Filter by "collect"
3. Submit contact form
4. **Expected:** GA4 event sent with:
   - `conversion_type: contact`
   - `email: jean@example.com`
   - `subject: Question`

#### Test 1.4: Newsletter Event
1. Open DevTools → Network tab
2. Filter by "collect"
3. Submit newsletter form
4. **Expected:** GA4 event sent with:
   - `conversion_type: newsletter`
   - `email: user@example.com`

### 2. Mixpanel Event Tracking

#### Test 2.1: Signup Event
1. Open browser console
2. Type: `mixpanel.get_distinct_id()`
3. Submit signup form
4. Go to Mixpanel dashboard
5. **Expected:** "Signup" event appears with email property

#### Test 2.2: Demo Request Event
1. Submit demo form
2. Go to Mixpanel dashboard
3. **Expected:** "Demo Request" event appears with company property

#### Test 2.3: Contact Event
1. Submit contact form
2. Go to Mixpanel dashboard
3. **Expected:** "Contact" event appears with subject property

#### Test 2.4: Newsletter Event
1. Submit newsletter form
2. Go to Mixpanel dashboard
3. **Expected:** "Newsletter Signup" event appears

---

## Security Testing

### 1. Rate Limiting

#### Test 1.1: Signup Rate Limit
```bash
for i in {1..6}; do
  echo "Attempt $i"
  curl -X POST http://localhost:3000/api/forms/signup \
    -H "Content-Type: application/json" \
    -d '{"email": "test@example.com", "password": "SecurePass123!"}'
  echo ""
done
```
**Expected:** 6th attempt returns 429 status

#### Test 1.2: Newsletter Rate Limit
```bash
for i in {1..11}; do
  echo "Attempt $i"
  curl -X POST http://localhost:3000/api/forms/newsletter \
    -H "Content-Type: application/json" \
    -d '{"email": "test@example.com"}'
  echo ""
done
```
**Expected:** 11th attempt returns 429 status

### 2. Input Sanitization

#### Test 2.1: HTML Injection
```bash
curl -X POST http://localhost:3000/api/forms/contact \
  -H "Content-Type: application/json" \
  -d '{
    "name": "<script>alert(1)</script>",
    "email": "test@example.com",
    "subject": "Test",
    "message": "Test message with <img src=x onerror=alert(1)>"
  }'
```
**Expected:** HTML is sanitized, no script execution

#### Test 2.2: Email Injection
```bash
curl -X POST http://localhost:3000/api/forms/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com\nBcc: attacker@example.com",
    "password": "SecurePass123!"
  }'
```
**Expected:** Email is sanitized

### 3. Validation

#### Test 3.1: Email Validation
```bash
# Invalid emails
curl -X POST http://localhost:3000/api/forms/newsletter \
  -d '{"email": "invalid"}'
curl -X POST http://localhost:3000/api/forms/newsletter \
  -d '{"email": "test@"}'
curl -X POST http://localhost:3000/api/forms/newsletter \
  -d '{"email": "@example.com"}'
```
**Expected:** All return 400 Bad Request

#### Test 3.2: Password Validation
```bash
# Weak passwords
curl -X POST http://localhost:3000/api/forms/signup \
  -d '{"email": "test@example.com", "password": "weak"}'
curl -X POST http://localhost:3000/api/forms/signup \
  -d '{"email": "test@example.com", "password": "NoSpecial123"}'
```
**Expected:** All return 400 Bad Request

---

## Performance Testing

### 1. Page Load Time

1. Open DevTools → Performance tab
2. Navigate to page with form
3. **Expected:** Page loads in < 2 seconds
4. **Expected:** Form is interactive within 1 second

### 2. Form Submission Time

1. Open DevTools → Network tab
2. Submit form
3. **Expected:** API response in < 500ms
4. **Expected:** Success message appears immediately

### 3. Bundle Size

```bash
npm run build
# Check bundle size in .next/static
```
**Expected:** Form components < 50KB total

---

## Accessibility Testing

### 1. Keyboard Navigation

1. Tab through form fields
2. **Expected:** All fields are reachable
3. **Expected:** Focus indicator is visible
4. **Expected:** Tab order is logical
5. **Expected:** Enter key submits form

### 2. Screen Reader

1. Open screen reader (NVDA, JAWS, VoiceOver)
2. Navigate form
3. **Expected:** All labels are read
4. **Expected:** Error messages are announced
5. **Expected:** Success messages are announced

### 3. Color Contrast

1. Use axe DevTools or WAVE
2. Check form colors
3. **Expected:** Contrast ratio > 4.5:1
4. **Expected:** No color-only information

### 4. Focus Indicators

1. Tab through form
2. **Expected:** Focus indicator is visible
3. **Expected:** Focus indicator has sufficient contrast
4. **Expected:** Focus indicator is not hidden

---

## Responsive Design Testing

### 1. Mobile (320px)

1. Resize browser to 320px
2. **Expected:** Form is single column
3. **Expected:** All fields are readable
4. **Expected:** Buttons are full width
5. **Expected:** No horizontal scrolling

### 2. Tablet (768px)

1. Resize browser to 768px
2. **Expected:** Form layout is optimized
3. **Expected:** Spacing is appropriate
4. **Expected:** All fields are visible

### 3. Desktop (1280px)

1. Resize browser to 1280px
2. **Expected:** Form layout is full
3. **Expected:** Spacing is generous
4. **Expected:** All fields are visible

---

## Browser Compatibility

### Desktop Browsers

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### Mobile Browsers

- [ ] iOS Safari (latest)
- [ ] Chrome Mobile (latest)
- [ ] Firefox Mobile (latest)
- [ ] Samsung Internet (latest)

---

## Checklist

- [ ] All forms render correctly
- [ ] All validations work
- [ ] All API routes work
- [ ] Rate limiting works
- [ ] Analytics tracking works
- [ ] Error messages display
- [ ] Success messages display
- [ ] Dark mode works
- [ ] Responsive design works
- [ ] Accessibility works
- [ ] Security features work
- [ ] Performance is acceptable
- [ ] Browser compatibility verified

---

## Reporting Issues

When reporting issues, include:

1. Form name (Signup, Demo, Contact, Newsletter)
2. Steps to reproduce
3. Expected behavior
4. Actual behavior
5. Browser and OS
6. Screenshots/videos if applicable

