# WooCommerce Review Reminder

## Product Overview

Build a production-ready WordPress + WooCommerce plugin called **WooCommerce Review Reminder**.

The plugin's primary purpose is to help WooCommerce store owners automatically request product reviews from customers after they purchase and receive their orders.

The goal is not to build a basic "send an email after X days" plugin. Build a polished, reliable, modern review-request automation system with a beautiful admin dashboard, flexible automation rules, professional email templates, analytics, and a strong user experience.

The plugin should feel like a premium SaaS product while remaining a native WordPress plugin.

---

# 1. Core Product Goal

The plugin should allow store owners to create automated review-request campaigns such as:

```text
Customer places order
        ↓
Order becomes Completed
        ↓
Wait 7 days
        ↓
Send review request email
        ↓
Customer clicks "Leave a Review"
        ↓
Customer lands on the purchased product review section
```

The store owner should be able to customize:

- When the reminder is triggered
- How long to wait
- Which customers receive the reminder
- Which products/categories are included
- Email subject
- Email content
- Button text
- Sender information
- Follow-up reminders
- Maximum number of reminders
- Campaign status
- Exclusion rules

---

# 2. Design & UI/UX Requirements

The admin interface must be visually polished and modern.

Use **shadcn/ui** as the primary design system for the admin interface.

The interface should feel inspired by modern SaaS dashboards such as Linear, Vercel, Stripe, and Notion.

Do NOT create a generic old-style WordPress admin interface.

## Design Principles

- Clean
- Minimal
- Spacious
- Professional
- Modern
- Fast
- Accessible
- Consistent
- Responsive
- Easy for non-technical store owners

Use:

- shadcn/ui components
- Cards
- Tabs
- Dropdown menus
- Dialogs
- Sheets
- Tooltips
- Badges
- Alerts
- Progress indicators
- Tables
- Empty states
- Skeleton loading states
- Toast notifications
- Confirmation dialogs

Avoid excessive gradients, excessive shadows, unnecessary animations, and visual clutter.

The interface should look premium but remain practical.

---

# 3. Admin Dashboard

Create a dedicated plugin dashboard.

Example:

```text
WooCommerce Review Reminder

Overview

┌─────────────────────────────────────────────────────────────┐
│ Review Requests Sent      12,842                            │
│ Review Requests Opened     8,214                            │
│ Reviews Generated          1,482                            │
│ Conversion Rate            11.5%                            │
└─────────────────────────────────────────────────────────────┘
```

Dashboard sections:

### Overview Cards

Display:

- Total review requests
- Requests sent
- Requests opened
- Requests clicked
- Reviews generated
- Conversion rate

### Performance Chart

Show review-request performance over time.

Filters:

- Last 7 days
- Last 30 days
- Last 90 days
- This year
- Custom range

### Recent Activity

Display recent events:

```text
John Smith
Purchased: Premium T-Shirt
Review request scheduled
2 hours ago
```

```text
Sarah Wilson
Review request opened
5 hours ago
```

```text
Michael Brown
Submitted a review
Yesterday
```

---

# 4. Campaign System

The core of the plugin should be a campaign/automation system.

Users should be able to create multiple campaigns.

Example:

```text
Campaigns

Post-Purchase Review Request
Active
Sent: 4,821
Reviews: 623
Conversion: 12.9%

VIP Customer Review Request
Active
Sent: 1,204
Reviews: 198
Conversion: 16.4%

Product Review Follow-up
Paused
```

Each campaign should have:

- Campaign name
- Description
- Status
- Trigger
- Delay
- Audience rules
- Product rules
- Email template
- Follow-up rules
- Statistics
- Created date
- Last modified date

---

# 5. Campaign Builder

Create a beautiful step-based campaign builder.

Example:

```text
1. Trigger
2. Audience
3. Timing
4. Email
5. Follow-up
6. Review
7. Activate
```

The campaign builder should be easy enough for a non-technical store owner.

---

# 6. Trigger Conditions

Initial supported triggers:

### Order Completed

When WooCommerce order status changes to:

```text
Completed
```

The campaign can start.

Also provide options for:

- Processing
- Completed

The default should be Completed.

Future architecture should allow additional triggers.

---

# 7. Timing Rules

Allow the merchant to configure:

```text
Send review request

[ 7 ] days after order completion
```

Supported units:

- Minutes
- Hours
- Days
- Weeks

Example:

```text
Send after:
7 days

Time:
10:00 AM
```

Allow optional preferred sending time.

If the WordPress site timezone is configured, respect the site's timezone.

---

# 8. Audience Rules

Allow merchants to decide who receives the campaign.

Conditions should include:

### Customer

- All customers
- Guest customers
- Registered customers
- Specific customer roles

### Order

- Minimum order value
- Maximum order value
- Order status
- Payment method
- Shipping method

### Customer history

- First-time customer
- Returning customer
- Number of previous orders

Build the rule system in a way that can be extended later.

---

# 9. Product Targeting

Allow campaigns to target:

### Products

- All products
- Specific products
- Product categories
- Product tags

Example:

```text
Campaign applies to:

✓ Electronics
✓ Accessories

Exclude:

✓ Gift Cards
```

The campaign should be able to determine which products from an order qualify for the campaign.

---

# 10. Exclusion Rules

Provide safeguards to prevent unwanted emails.

Examples:

- Do not send if customer already reviewed the product
- Do not send if customer has opted out
- Do not send if order was refunded
- Do not send if order was cancelled
- Do not send duplicate requests
- Do not send more than X requests per order
- Do not send to excluded products
- Do not send to excluded customer roles

These checks should happen before the email is actually sent.

---

# 11. Review Detection

The plugin should detect whether the customer has already submitted a review for the relevant product.

If a review already exists:

Do not send another reminder for that product.

Example:

```text
Order contains:

Product A ✓ Already reviewed
Product B ✗ Not reviewed
Product C ✗ Not reviewed
```

Only Product B and Product C should remain eligible.

---

# 12. Email Template Builder

Create a beautiful email template editor.

The user should be able to customize:

### Email Subject

Example:

```text
How are you enjoying your {{product_name}}?
```

### Email Content

Support dynamic variables.

Example:

```text
Hi {{customer_first_name}},

Thank you for your recent purchase!

We'd love to hear what you think about
{{product_name}}.

Your feedback helps other customers
make better purchasing decisions.

[ Leave a Review ]

Thank you,
{{store_name}}
```

---

# 13. Dynamic Variables

Support variables such as:

```text
{{customer_first_name}}
{{customer_last_name}}
{{customer_name}}
{{customer_email}}

{{order_number}}
{{order_date}}

{{product_name}}
{{product_url}}
{{product_image}}
{{review_url}}

{{store_name}}
{{store_url}}
{{unsubscribe_url}}
```

Create a convenient variable picker in the email editor.

The user should be able to click a variable and insert it into the email.

---

# 14. Email Templates

Provide several professional default templates.

For example:

### Simple Review Request

Clean and minimal.

### Product Feedback

Product-focused email with product image.

### Friendly Follow-up

More conversational design.

### Thank You + Review

Focus on customer appreciation.

Templates should be customizable.

---

# 15. Email Preview

Provide a live preview.

Desktop:

```text
┌───────────────────────────────┐
│        Store Logo             │
│                               │
│  Hi John,                     │
│                               │
│  How are you enjoying         │
│  your new product?            │
│                               │
│     [ Leave a Review ]        │
│                               │
│  Thank you for shopping       │
│  with us.                     │
└───────────────────────────────┘
```

Also provide:

- Desktop preview
- Mobile preview
- Send test email

---

# 16. Email Sending Architecture

Do not rely exclusively on PHP's default `mail()` function.

Build an email delivery abstraction layer so different email providers can be supported.

Initial support:

- WordPress mail
- WooCommerce email system

The architecture should make it easy to add:

- SMTP
- SendGrid
- Mailgun
- Amazon SES
- Brevo

later.

Provide clear email configuration and test functionality.

---

# 17. Review Button

The email's primary CTA should take the customer directly to the appropriate product review location.

For example:

```text
https://example.com/product/product-name/#reviews
```

If the theme uses a different review implementation, design the URL-generation layer to be extendable.

---

# 18. Multiple Products Per Order

Handle orders containing multiple products intelligently.

Example:

Order #1045:

```text
Product A
Product B
Product C
```

The system should decide whether to:

### Option A

Send one email containing all eligible products.

OR

### Option B

Send separate review requests.

Provide a campaign setting:

```text
Review request strategy

○ One email containing all products
○ Separate request for each product
```

The default should be one email containing all eligible products.

---

# 19. Follow-up System

Allow merchants to create follow-up reminders.

Example:

```text
Initial request
↓
7 days
↓
Follow-up #1
↓
7 days
↓
Follow-up #2
```

Stop follow-ups when:

- Customer submits a review
- Maximum reminders reached
- Order is refunded
- Customer unsubscribes

Example:

```text
Maximum reminders:
2
```

---

# 20. Review Request Queue

Create a queue system.

Example:

```text
Scheduled Review Requests

Customer        Product          Scheduled       Status

John Smith      T-Shirt          Aug 28          Scheduled
Sarah Brown     Headphones       Aug 29          Scheduled
Michael Lee     Keyboard         Aug 30          Scheduled
```

Statuses:

- Scheduled
- Processing
- Sent
- Failed
- Cancelled
- Reviewed

Allow filtering and searching.

---

# 21. Request Details

Clicking a request should open a detailed view.

Show:

```text
Customer
Order
Product
Campaign
Scheduled date
Sent date
Email status
Open status
Click status
Review status
```

Timeline:

```text
Order completed
      ↓
Request scheduled
      ↓
Email sent
      ↓
Email opened
      ↓
Review link clicked
      ↓
Review submitted
```

---

# 22. Analytics

Provide meaningful analytics.

Metrics:

- Requests scheduled
- Emails sent
- Emails delivered
- Emails opened
- CTA clicks
- Reviews submitted
- Conversion rate
- Average time to review

Charts:

- Requests over time
- Reviews over time
- Conversion rate
- Campaign comparison

Campaign comparison:

```text
Campaign                  Sent    Reviews    Conversion

Post Purchase             4,821   623        12.9%
VIP Customers             1,204   198        16.4%
Product Follow-up         2,431   287        11.8%
```

---

# 23. Reviews Dashboard

Create a section showing review performance.

Display:

- Total reviews generated
- Average rating
- Reviews generated by campaign
- Top reviewed products
- Products with no reviews
- Review conversion rate

Example:

```text
Top Products by New Reviews

Product              New Reviews

Premium Headphones        82
Running Shoes             67
Smart Watch               51
Laptop Stand              43
```

---

# 24. Settings

Create a clean settings interface using tabs.

Suggested tabs:

```text
General
Email
Automation
Reviews
Privacy
Advanced
```

### General

- Enable/disable plugin
- Default campaign
- Timezone
- Date format

### Email

- From name
- From email
- Reply-to
- Email provider
- Test email

### Automation

- Default delay
- Default sending time
- Maximum reminders
- Retry settings

### Reviews

- Review detection
- Review URL behavior
- Review request strategy

### Privacy

- Unsubscribe behavior
- Data retention
- Logging settings

### Advanced

- Debug logging
- Cron status
- Database cleanup
- Developer mode

---

# 25. Unsubscribe System

Every marketing/review-request email should provide an unsubscribe mechanism.

Example:

```text
Don't want to receive review reminders?

Unsubscribe
```

The customer should be added to an internal suppression list.

Once unsubscribed:

Do not send future review-request emails.

Provide an admin interface to view suppressed customers.

---

# 26. WordPress Cron / Scheduled Processing

Do not attempt to send every email directly during checkout/order completion.

Use scheduled processing.

Recommended architecture:

```text
Order Completed
       ↓
Create Review Request
       ↓
Schedule Request
       ↓
WP-Cron / Action Scheduler
       ↓
Process Queue
       ↓
Send Email
       ↓
Track Result
```

Prefer **WooCommerce Action Scheduler** where appropriate because WooCommerce already provides it.

The system should be resilient to:

- Failed cron jobs
- Duplicate execution
- Temporary email failures
- Site traffic spikes

---

# 27. Retry Logic

If an email fails:

```text
Attempt 1 → Failed
Attempt 2 → Retry
Attempt 3 → Retry
```

Allow configurable retry count.

Record the failure reason.

Example:

```text
Email failed

Reason:
SMTP connection timeout
```

---

# 28. Database Architecture

Do not store everything in WordPress options.

Use custom database tables where appropriate for scalable data such as:

- Campaigns
- Review requests
- Queue items
- Events
- Suppression records
- Analytics

Use proper indexes.

Design the database architecture for stores with:

- 10,000 orders
- 100,000 orders
- 1,000,000+ orders

Avoid inefficient queries.

---

# 29. Security Requirements

Follow WordPress security best practices.

Implement:

- Capability checks
- Nonce verification
- Sanitization
- Validation
- Escaping
- Prepared SQL queries
- Secure AJAX/REST endpoints
- Permission checks
- No direct file access

Never trust admin-submitted data.

All database queries must use `$wpdb->prepare()` where applicable.

---

# 30. WordPress Coding Standards

Follow:

- WordPress Coding Standards
- WooCommerce coding standards
- PHP 8.1+ compatibility
- WordPress internationalization
- Proper escaping
- Proper plugin architecture

Avoid modifying WooCommerce core files.

Avoid modifying theme files.

Use hooks and filters wherever possible.

---

# 31. Plugin Architecture

Build the plugin with a modular architecture.

Suggested structure:

```text
woocommerce-review-reminder/
│
├── woocommerce-review-reminder.php
│
├── app/
│   ├── Admin/
│   ├── Campaigns/
│   ├── Emails/
│   ├── Queue/
│   ├── Reviews/
│   ├── Analytics/
│   ├── Database/
│   ├── REST/
│   ├── Cron/
│   ├── Privacy/
│   └── Core/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── admin/
│
├── templates/
│
├── languages/
│
├── uninstall.php
│
└── readme.txt
```

You may improve this architecture if you have a better maintainable structure.

The architecture must be extensible because future versions may introduce:

- SMS
- WhatsApp
- Telegram
- AI-generated review requests
- Advanced segmentation
- A/B testing
- External email providers
- Review incentives
- Loyalty integrations

---

# 32. React + shadcn Admin UI

Use React for the modern admin application where appropriate.

Use shadcn/ui components.

Suggested components:

- Button
- Card
- Input
- Select
- Checkbox
- Switch
- Tabs
- Dialog
- Sheet
- Dropdown Menu
- Popover
- Tooltip
- Badge
- Table
- Calendar
- Date Picker
- Command
- Alert Dialog
- Toast/Sonner
- Progress
- Skeleton

The UI should follow a consistent design system.

Do not build custom UI components when an appropriate shadcn component exists.

---

# 33. Responsive Design

The dashboard should work well on:

- Desktop
- Laptop
- Tablet

Mobile support should be considered, especially for:

- Tables
- Campaign builder
- Settings
- Analytics

Tables should gracefully transform on smaller screens.

---

# 34. Empty States

Every section needs a useful empty state.

Example:

```text
No campaigns yet

Create your first review campaign
and start collecting more customer feedback.

[ Create Campaign ]
```

Do not leave blank screens.

---

# 35. Onboarding

After plugin activation, show a lightweight onboarding flow.

Example:

```text
Welcome to WooCommerce Review Reminder

Step 1
Choose when to request reviews

Step 2
Customize your email

Step 3
Activate your campaign
```

At the end:

```text
Your first review campaign is ready.

[ Activate Campaign ]
```

Also create a default campaign automatically if appropriate.

---

# 36. Notifications

Use modern toast notifications.

Examples:

```text
Campaign created successfully.
```

```text
Review campaign activated.
```

```text
Test email sent successfully.
```

```text
Settings saved.
```

Error messages should be clear and actionable.

Bad:

```text
Error 500
```

Good:

```text
We couldn't send the test email.
Please check your email configuration and try again.
```

---

# 37. Accessibility

Follow WCAG-friendly practices.

Ensure:

- Keyboard navigation
- Proper labels
- Focus states
- Screen-reader-friendly controls
- Sufficient contrast
- Accessible dialogs
- Accessible form validation

Do not rely only on color to communicate status.

---

# 38. Internationalization

The plugin must be translation-ready.

Use WordPress translation functions properly.

Text domain:

```text
woocommerce-review-reminder
```

Do not hardcode user-facing strings.

---

# 39. Performance

Performance is extremely important.

Do not:

- Load assets on every WordPress admin page
- Run expensive database queries on every request
- Process large queues synchronously
- Perform heavy calculations during checkout

Only load plugin assets on the plugin's admin pages.

Use pagination for large datasets.

Use indexed database queries.

Cache expensive analytics queries where appropriate.

---

# 40. WooCommerce Compatibility

The plugin must gracefully detect whether WooCommerce is active.

If WooCommerce is not installed:

Show:

```text
WooCommerce Review Reminder requires WooCommerce.

[ Install WooCommerce ]
```

Do not cause fatal errors if WooCommerce is disabled.

Support current stable WooCommerce APIs and avoid deprecated functionality.

---

# 41. Developer Experience

The codebase should be easy for another developer to understand.

Include:

- Clear class names
- Clear comments where necessary
- Separation of concerns
- Reusable services
- Hooks and filters
- Documentation for important services
- Consistent naming conventions

Do not over-engineer simple functionality.

---

# 42. Extensibility

Create hooks and filters for important functionality.

Examples:

```php
apply_filters(
    'wrr_review_request_delay',
    $delay,
    $order,
    $campaign
);
```

```php
do_action(
    'wrr_review_request_sent',
    $request_id
);
```

Use a consistent plugin prefix:

```text
wrr_
```

PHP classes should use a namespace to avoid conflicts.

---

# 43. Logging

Provide a developer-friendly logging system.

Log:

- Campaign execution
- Scheduled requests
- Email attempts
- Email failures
- Review detection
- Cron processing
- Important API errors

Allow debug logging to be enabled from Advanced Settings.

Do not expose sensitive customer information unnecessarily in logs.

---

# 44. Uninstall Behavior

Create a proper `uninstall.php`.

On uninstall, provide a setting:

```text
Delete plugin data on uninstall

[ ] Delete all plugin data when plugin is permanently deleted
```

Default should be OFF.

Do not delete customer/order data belonging to WooCommerce.

---

# 45. MVP Scope

Do NOT attempt to implement every advanced feature initially.

The first production-ready MVP should include:

### Core

- Plugin activation/deactivation
- WooCommerce compatibility check
- Admin dashboard
- Campaign creation
- Campaign activation/deactivation
- Order-completed trigger
- Configurable delay
- Product targeting
- Customer targeting
- Review detection
- Review request queue
- Email template
- Dynamic variables
- Test email
- Review CTA
- Scheduled processing
- Retry logic
- Basic analytics
- Unsubscribe
- Settings
- Logging

The architecture must make future Pro features easy to add.

---

# 46. Future Pro Features

Design the code so these can be added later without rewriting the core system.

Potential Pro features:

- Multiple campaigns
- Advanced conditional rules
- Multiple follow-ups
- A/B testing
- Advanced analytics
- SMTP integrations
- SendGrid
- Mailgun
- Amazon SES
- Brevo
- Twilio
- WhatsApp
- SMS
- AI-generated email content
- Review incentives
- Coupon generation
- Customer segmentation
- VIP campaigns
- Review widgets
- Google Review integration
- Review request scheduling
- Multi-language emails

---

# 47. Visual Quality Requirements

This is extremely important.

Do not produce a basic developer-looking interface.

The plugin should look like a professionally designed commercial product.

Use:

- Consistent spacing
- Strong typography hierarchy
- Subtle borders
- Clean cards
- Clear visual hierarchy
- Excellent empty states
- Helpful microcopy
- Intuitive navigation
- Consistent iconography
- Clear success/error states

Use Lucide icons where appropriate.

Avoid:

- Giant colorful gradients
- Excessive rounded cards
- Excessive animations
- Unnecessary decorative elements
- Cluttered dashboards
- Dense forms
- Tiny text

The design should prioritize usability over decoration.

---

# 48. Suggested Admin Navigation

Use a sidebar:

```text
Review Reminder

Overview
Campaigns
Requests
Reviews
Analytics
Templates
Settings
```

At the bottom:

```text
Help
Documentation
Support
```

Show plugin version and WooCommerce compatibility where appropriate.

---

# 49. Dashboard Experience

The dashboard should immediately answer:

1. How many review requests were sent?
2. How many customers interacted with them?
3. How many reviews were generated?
4. Which campaign is performing best?
5. Which products need more reviews?

A store owner should understand the value of the plugin within 10 seconds of opening the dashboard.

---

# 50. Development Approach

Build the project in phases.

## Phase 1

Project architecture and plugin bootstrap.

## Phase 2

Database and core services.

## Phase 3

WooCommerce order integration.

## Phase 4

Campaign system.

## Phase 5

Review request queue.

## Phase 6

Email system.

## Phase 7

React + shadcn admin UI.

## Phase 8

Analytics.

## Phase 9

Settings and privacy.

## Phase 10

Testing, security, optimization, and WordPress.org preparation.

After each phase:

- Verify functionality
- Check for PHP errors
- Check browser console
- Test WooCommerce integration
- Test edge cases
- Keep the code clean
- Do not break existing functionality

---

# 51. Testing Requirements

Test at minimum:

### Order scenarios

- Guest checkout
- Registered customer
- Single-product order
- Multiple-product order
- Refunded order
- Cancelled order
- Failed order
- Partially refunded order

### Review scenarios

- Customer already reviewed product
- Customer hasn't reviewed
- Multiple products
- Multiple orders
- Duplicate requests

### Email scenarios

- Successful email
- Failed email
- Retry
- Invalid email
- Unsubscribe

### Campaign scenarios

- Active campaign
- Paused campaign
- Deleted campaign
- Multiple campaigns
- Conflicting campaigns

### Performance

Test with large numbers of:

- Orders
- Products
- Customers
- Review requests

---

# 52. WordPress.org Preparation

The plugin should eventually be suitable for submission to WordPress.org.

Prepare:

- Proper plugin headers
- GPL-compatible licensing
- `readme.txt`
- Translation-ready strings
- No prohibited tracking
- Secure coding
- No obfuscated code
- Proper external service disclosure
- Proper admin notices
- Proper uninstall handling

---

# 53. Final Product Philosophy

The most important principle is:

> Make collecting customer reviews effortless.

A store owner should be able to install the plugin, create or activate a campaign, and start sending review requests within a few minutes.

Do not make users understand technical concepts such as cron jobs, database queues, hooks, or email delivery architecture.

The complexity should exist in the code, not in the user interface.

Build the product so that the user experience feels simple:

```text
Install
   ↓
Create Campaign
   ↓
Choose Delay
   ↓
Customize Email
   ↓
Activate
   ↓
Collect More Reviews
```

The plugin should be reliable, fast, secure, scalable, visually polished, and genuinely useful for WooCommerce store owners.