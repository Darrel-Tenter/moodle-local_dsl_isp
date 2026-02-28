<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for local_dsl_isp.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['pluginname'] = 'ISP Manager';
$string['plugindescription'] = 'Automates ISP & Supporting Document review course lifecycle management for IDD clients.';

// Capabilities.
$string['dsl_isp:view'] = 'View ISP Manager and client list';
$string['dsl_isp:manageclients'] = 'Add, edit, and archive clients';
$string['dsl_isp:managedsps'] = 'Assign and remove DSPs from clients';
$string['dsl_isp:resetcompletion'] = 'Manually reset DSP course completion';
$string['dsl_isp:viewhistory'] = 'View historical completion log';
$string['dsl_isp:managetenants'] = 'Enable/disable ISP Manager per tenant';
$string['dsl_isp:managetemplates'] = 'Configure template course and global settings';

// Navigation.
$string['ispmanager'] = 'ISP Manager';
$string['clientlist'] = 'Client list';
$string['addnewclient'] = 'Add new client';
$string['editclient'] = 'Edit client';
$string['managedsps'] = 'Manage DSPs';
$string['updatedocuments'] = 'Update documents';
$string['viewclient'] = 'View client';
$string['tenantmanagement'] = 'Tenant management';

// Client list page.
$string['clientlisttitle'] = 'ISP Manager';
$string['clientlistheading'] = 'ISP Manager';
$string['noclients'] = 'No clients found.';
$string['noclientsmatch'] = 'No clients match your search criteria.';
$string['searchclients'] = 'Search clients...';
$string['filterbyservicetype'] = 'Service type';
$string['filterbycompletionstatus'] = 'Completion status';
$string['allservicetypes'] = 'All service types';
$string['allstatuses'] = 'All statuses';
$string['statuscomplete'] = 'Complete';
$string['statusinprogress'] = 'In progress';
$string['statusoverdue'] = 'Overdue';
$string['statusnotstarted'] = 'Not started';
$string['clientcount'] = '{$a->count} client(s)';
$string['dspcount'] = '{$a->completed} of {$a->total} DSPs completed';
$string['planyear'] = 'Plan year: {$a->start} – {$a->end}';
$string['anniversarydate'] = 'Anniversary: {$a}';

// Service types.
$string['servicetype_residential'] = 'Residential';
$string['servicetype_attendant_care'] = 'Attendant Care';
$string['servicetype_dsa'] = 'DSA';
$string['servicetype_foster_care'] = 'Foster Care';
$string['servicetype_other'] = 'Other';

// Client form - Section headers.
$string['clientinformation'] = 'Client information';
$string['ispdocuments'] = 'ISP documents';
$string['assigndsps'] = 'Assign DSPs';

// Client form - Fields.
$string['firstname'] = 'First name';
$string['lastname'] = 'Last name';
$string['servicetype'] = 'Service type';
$string['anniversarydatefield'] = 'ISP anniversary date';
$string['anniversarydatefield_help'] = 'The date the client was first added to the ODDS system. This is the plan year start date and determines when annual completion resets occur.';

// Client form - Document slots.
$string['docslot_onepageprofile'] = 'One Page Profile';
$string['docslot_individualsupportplan'] = 'Individual Support Plan';
$string['docslot_personcenteredinfo'] = 'Person Centered Information';
$string['docslot_safetyplan'] = 'Safety Plan / Risk Management Plan';
$string['docslot_providerrms'] = 'Provider Risk Management Strategies';
$string['docslot_rit'] = 'Risk Identification Tool';
$string['docslot_actionplan'] = 'Action Plan';
$string['docslot_supportdoc'] = 'Support Document / Protocol';
$string['docslot_required'] = '(required)';
$string['docslot_optional'] = '(optional)';
$string['docslot_conditional'] = '(conditional)';
$string['includedocument'] = 'Include this document';
$string['addanother'] = 'Add another';
$string['removedocument'] = 'Remove';
$string['documentname'] = 'Document name';
$string['documentdate'] = 'Document date';
$string['datereadfromdoc'] = 'Read from document — please verify';
$string['datecouldnotread'] = 'Could not read date — please enter manually';
$string['acceptedfiletypes'] = 'PDF files only';
$string['maxfilesize'] = 'Maximum file size: {$a} MB';

// Client form - DSP assignment.
$string['searchdsps'] = 'Search DSPs in your organization...';
$string['selecteddsps'] = 'Selected DSPs';
$string['nodspsselected'] = 'No DSPs selected';
$string['dspscanbeaddedlater'] = 'DSPs can also be assigned or removed after the client is created.';

// Client form - Validation.
$string['error_firstnamerequired'] = 'First name is required.';
$string['error_lastnamerequired'] = 'Last name is required.';
$string['error_servicetyperequired'] = 'Service type is required.';
$string['error_anniversarydaterequired'] = 'Anniversary date is required.';
$string['error_anniversarydatefuture'] = 'Anniversary date cannot be in the future.';
$string['error_clientnameexists'] = 'A client with this name already exists in your organization.';
$string['error_documentrequired'] = 'This document is required.';
$string['error_invalidfiletype'] = 'Only PDF files are accepted.';
$string['error_filesizeexceeded'] = 'File size exceeds the maximum allowed ({$a} MB).';
$string['error_documentnamerequired'] = 'Document name is required.';
$string['error_documentdaterequired'] = 'Document date is required.';
$string['error_invaliddateformat'] = 'Invalid date format.';

// Client detail page.
$string['clientdetailtitle'] = '{$a->firstname} {$a->lastname}';
$string['clientsummary'] = 'Client summary';
$string['documentsection'] = 'Documents';
$string['dspassignments'] = 'DSP assignments';
$string['completionhistory'] = 'Completion history';
$string['editclientinfo'] = 'Edit client info';
$string['archiveclient'] = 'Archive client';
$string['unarchiveclient'] = 'Unarchive client';
$string['currentplanyear'] = 'Current plan year';
$string['documentslot'] = 'Document';
$string['filename'] = 'Filename';
$string['lastupdated'] = 'Last updated';
$string['nodocuments'] = 'No documents uploaded.';

// DSP assignment table.
$string['dspname'] = 'DSP name';
$string['dateassigned'] = 'Date assigned';
$string['completionstatus'] = 'Completion status';
$string['actions'] = 'Actions';
$string['adddsp'] = 'Add DSP';
$string['removedsp'] = 'Remove';
$string['resetcompletion'] = 'Reset completion';
$string['nodspsassigned'] = 'No DSPs assigned to this client.';
$string['completed'] = 'Completed {$a}';
$string['notcompleted'] = 'Not completed';
$string['inprogress'] = 'In progress';

// DSP form.
$string['assigndsp'] = 'Assign DSP';
$string['selectdsp'] = 'Select DSP';
$string['searchusers'] = 'Search users...';
$string['nousersmatching'] = 'No users matching "{$a}"';

// Confirmation dialogs.
$string['confirm_removedsp'] = 'Remove this DSP from the client?';
$string['confirm_removedsp_desc'] = 'This will remove {$a->dspname} from {$a->clientname}. They will lose access to the ISP course immediately. Their completion history will be preserved.';
$string['confirm_resetcompletion'] = 'Reset completion for this DSP?';
$string['confirm_resetcompletion_desc'] = 'This will require {$a->dspname} to re-complete the ISP course for {$a->clientname}. Their current completion will be archived.';
$string['confirm_archiveclient'] = 'Archive this client?';
$string['confirm_archiveclient_desc'] = 'This will hide {$a} from the active client list. All data and completion history will be preserved.';

// Completion history.
$string['planyearcolumn'] = 'Plan year';
$string['completeddatecolumn'] = 'Completed';
$string['archivedcolumn'] = 'Archived';
$string['notescolumn'] = 'Notes';
$string['nocompletionhistory'] = 'No completion history recorded.';
$string['manualreset'] = 'Manual reset';
$string['gap'] = 'Not completed';

// Admin settings.
$string['settings'] = 'ISP Manager settings';
$string['templatecourseid'] = 'Template course ID';
$string['templatecourseid_desc'] = 'The course ID of the global ISP template course. This course is duplicated for each new client.';
$string['studentroleid'] = 'Student role ID';
$string['studentroleid_desc'] = 'The role ID for the student role used when enrolling DSPs. Default is the standard Moodle student role.';
$string['maxfilesizembsetting'] = 'Maximum file size (MB)';
$string['maxfilesizembsetting_desc'] = 'Maximum file size per document upload in megabytes.';
$string['renewalnotifyemail'] = 'Renewal notification email';
$string['renewalnotifyemail_desc'] = 'Additional email address to CC on renewal notifications (e.g. DSL support inbox). Leave empty to disable.';
$string['error_invalidtemplatecourse'] = 'The specified template course does not exist.';
$string['error_invalidstudentroleid'] = 'The specified student role does not exist.';

// Tenant management.
$string['tenantmanagementtitle'] = 'ISP Manager - Tenant Management';
$string['tenantmanagementheading'] = 'ISP Manager Tenant Management';
$string['tenantname'] = 'Tenant';
$string['ispapienabled'] = 'ISP Manager enabled';
$string['enabledby'] = 'Enabled by';
$string['enableddate'] = 'Enabled date';
$string['enableispmanager'] = 'Enable ISP Manager';
$string['disableispmanager'] = 'Disable ISP Manager';
$string['ispmanagerenabled'] = 'ISP Manager is now enabled for {$a}.';
$string['ispmanagerdisabled'] = 'ISP Manager is now disabled for {$a}.';
$string['notenants'] = 'No tenants found.';

// Feature gate.
$string['featurenotenabled'] = 'ISP Manager is not enabled for your organization.';
$string['featurenotenabled_desc'] = 'Please contact Direct Support Learning support to enable this feature.';
$string['contactsupport'] = 'Contact support';

// Events.
$string['eventclientcreated'] = 'Client created';
$string['eventclientupdated'] = 'Client updated';
$string['eventclientarchived'] = 'Client archived';
$string['eventdspassigned'] = 'DSP assigned';
$string['eventdspremoved'] = 'DSP removed';
$string['eventclientrenewed'] = 'Client renewed';
$string['eventcompletionmanuallyreset'] = 'Completion manually reset';
$string['eventdocumentsupdated'] = 'Documents updated';

// Scheduled task.
$string['task_annualrenewal'] = 'ISP annual renewal';
$string['task_annualrenewal_desc'] = 'Archives completion data and resets course completion for clients whose ISP anniversary date falls on the current date.';
$string['renewalprocessed'] = 'Annual renewal processed for {$a->count} client(s).';
$string['renewalskipped'] = 'No clients due for renewal today.';
$string['renewalerror'] = 'Error processing renewal for client {$a->clientid}: {$a->error}';

// Notifications.
$string['notification_renewal_subject'] = 'ISP annual renewal processed';
$string['notification_renewal_body'] = 'The following clients had their annual ISP renewal processed today:

{$a->clientlist}

All assigned DSPs must now re-complete their ISP review courses.';
$string['notification_renewal_client'] = '- {$a->firstname} {$a->lastname} ({$a->dspcount} DSPs)';

// Success messages.
$string['clientcreated'] = 'Client "{$a}" has been created successfully.';
$string['clientupdated'] = 'Client "{$a}" has been updated successfully.';
$string['clientarchived'] = 'Client "{$a}" has been archived.';
$string['clientunarchived'] = 'Client "{$a}" has been unarchived.';
$string['dspassigned'] = '{$a->dspname} has been assigned to {$a->clientname}.';
$string['dspremoved'] = '{$a->dspname} has been removed from {$a->clientname}.';
$string['completionreset'] = 'Completion has been reset for {$a->dspname}.';
$string['documentsupdated'] = 'Documents have been updated for {$a}.';

// Error messages.
$string['error_clientnotfound'] = 'Client not found.';
$string['error_dspnotfound'] = 'DSP not found.';
$string['error_usernotintenant'] = 'The selected user is not a member of your organization.';
$string['error_dspalreadyassigned'] = 'This DSP is already assigned to this client.';
$string['error_coursecreationfailed'] = 'Failed to create the ISP course. Please try again or contact support.';
$string['error_enrollmentfailed'] = 'Failed to enroll DSP in the course. Please try again.';
$string['error_unenrollmentfailed'] = 'Failed to unenroll DSP from the course. Please try again.';
$string['error_completionresetfailed'] = 'Failed to reset completion. Please try again.';
$string['error_documentreplacefailed'] = 'Failed to replace document. Please try again.';
$string['error_templatecoursenotconfigured'] = 'The template course has not been configured. Please contact your DSL administrator.';
$string['error_tenantnotfound'] = 'Your organization could not be determined. Please contact support.';
$string['error_permissiondenied'] = 'You do not have permission to perform this action.';

// Privacy.
$string['privacy:metadata:dsl_isp_client'] = 'Stores IDD client information including names and service types.';
$string['privacy:metadata:dsl_isp_client:firstname'] = 'The client\'s first name.';
$string['privacy:metadata:dsl_isp_client:lastname'] = 'The client\'s last name.';
$string['privacy:metadata:dsl_isp_client:servicetype'] = 'The type of service the client receives.';
$string['privacy:metadata:dsl_isp_client:usermodified'] = 'The user who last modified this record.';
$string['privacy:metadata:dsl_isp_dsp'] = 'Stores DSP-to-client assignment records.';
$string['privacy:metadata:dsl_isp_dsp:userid'] = 'The user ID of the assigned DSP.';
$string['privacy:metadata:dsl_isp_dsp:clientid'] = 'The client ID the DSP is assigned to.';
$string['privacy:metadata:dsl_isp_dsp:timeassigned'] = 'When the DSP was assigned to the client.';
$string['privacy:metadata:dsl_isp_dsp:timeunassigned'] = 'When the DSP was removed from the client.';
$string['privacy:metadata:dsl_isp_dsp:assignedby'] = 'The user ID of the admin who made the assignment.';
$string['privacy:metadata:dsl_isp_dsp:unassignedby'] = 'The user ID of the admin who removed the assignment.';
$string['privacy:metadata:dsl_isp_completion_log'] = 'Historical archive of annual ISP completion records for audit purposes.';
$string['privacy:metadata:dsl_isp_completion_log:userid'] = 'The user ID of the DSP whose completion was archived.';
$string['privacy:metadata:dsl_isp_completion_log:clientid'] = 'The client ID for this completion record.';
$string['privacy:metadata:dsl_isp_completion_log:planyearstart'] = 'The start date of the plan year.';
$string['privacy:metadata:dsl_isp_completion_log:planyearend'] = 'The end date of the plan year.';
$string['privacy:metadata:dsl_isp_completion_log:timecompleted'] = 'When the DSP completed the ISP review, if at all.';
$string['privacy:metadata:dsl_isp_completion_log:timearchived'] = 'When this record was archived by the system.';
$string['privacy:metadata:dsl_isp_tenant_settings'] = 'Per-tenant configuration for ISP Manager feature access.';
$string['privacy:metadata:dsl_isp_tenant_settings:enabledby'] = 'The user ID of the admin who enabled the feature.';

// Miscellaneous.
$string['loading'] = 'Loading...';
$string['saving'] = 'Saving...';
$string['processing'] = 'Processing...';
$string['cancel'] = 'Cancel';
$string['save'] = 'Save';
$string['confirm'] = 'Confirm';
$string['yes'] = 'Yes';
$string['no'] = 'No';
$string['back'] = 'Back';
$string['backtoclientlist'] = 'Back to client list';
$string['viewcourse'] = 'View course';
$string['unknown'] = 'Unknown';
$string['never'] = 'Never';
$string['today'] = 'Today';
$string['overdue'] = 'Overdue';
$string['active'] = 'Active';
$string['archived'] = 'Archived';
