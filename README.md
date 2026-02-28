# ISP Manager for Moodle Workplace

**local_dsl_isp** — Automates Individual Support Plan (ISP) review course lifecycle for Oregon I/DD service providers.

## Overview

ISP Manager eliminates a 16-step manual process for setting up ISP compliance tracking. It provides:

- **One-click client setup** — Create a complete ISP review course from a single form
- **Automated annual renewal** — Reset DSP completions on each client's anniversary date
- **DSP assignment management** — Assign/remove Direct Support Professionals per client
- **7-10 year audit trail** — Historical completion records for ODDS compliance audits
- **Multi-tenant isolation** — Each agency sees only their own clients and DSPs

## Requirements

- Moodle Workplace 5.1.3+
- PHP 8.3+
- [local_recompletion](https://moodle.org/plugins/local_recompletion) plugin

## Installation

1. Extract to `local/dsl_isp/` in your Moodle installation
2. Run the Moodle upgrade (Site Administration → Notifications or `php admin/cli/upgrade.php`)
3. Configure the plugin at Site Administration → Plugins → Local plugins → ISP Manager:
   - Set the template course ID
   - Verify the student role ID
4. Create the ISP template course (see Configuration below)
5. Enable ISP Manager for tenants via the tenant management page

## Configuration

### Template Course Setup

Before tenants can create clients, a global ISP template course must be created:

1. Create a new course in a DSL-managed category (hidden from tenants)
2. Add File resource activities for each document slot (1-8) with placeholder PDFs
3. Set completion criteria: all File resources must be viewed
4. Add a "Click here to finish" Page activity with completion on view
5. Add a coursecertificate activity
6. Note the course ID and enter it in the plugin settings

### local_recompletion Settings

Configure local_recompletion with these settings:

| Setting | Value |
|---------|-------|
| Recompletion type | Do not automatically reset |
| Reset activity completions | Yes |
| Reset course completion | Yes |
| Reset grades | No |
| Archive completions | No |
| Notify users on reset | No |

### Tenant Category

Each tenant must have a category configured in Moodle Workplace tenant settings. ISP courses are created in an "ISP & Supporting Documents" subcategory under the tenant's category (created automatically).

## Usage

### For Tenant Admins

1. Navigate to ISP Manager from the main menu
2. Click "Add New Client" to create a new ISP review course
3. Upload the required ISP documents (slots 1-5 required, 6-8 optional)
4. Assign DSPs who need to complete the ISP review
5. Monitor completion status from the client list

### Document Slots

| # | Document | Required |
|---|----------|----------|
| 1 | One Page Profile | Yes |
| 2 | Individual Support Plan | Yes |
| 3 | Person Centered Information | Yes |
| 4 | Safety Plan / Risk Management Plan | Yes |
| 5 | Provider Risk Management Strategies | Yes |
| 6 | Risk Identification Tool | No |
| 7 | Action Plan(s) | No |
| 8 | Support Document(s) / Protocol(s) | No |

### Annual Renewal

A scheduled task runs daily at 2:00 AM to process anniversary renewals:

1. Archives current completion status to the audit log
2. Resets course completion for all active DSPs
3. Notifies tenant admin of processed renewals

## Capabilities

| Capability | Description |
|------------|-------------|
| `local/dsl_isp:view` | View ISP Manager and client list |
| `local/dsl_isp:manageclients` | Add, edit, archive clients |
| `local/dsl_isp:managedsps` | Assign and remove DSPs |
| `local/dsl_isp:resetcompletion` | Manually reset DSP completion |
| `local/dsl_isp:viewhistory` | View historical completion log |
| `local/dsl_isp:managetenants` | Enable/disable per tenant (DSL admin) |
| `local/dsl_isp:managetemplates` | Configure template and settings |

## License

This plugin is proprietary software developed for Direct Support Learning.

## Support

Contact: support@directsupportlearning.com
