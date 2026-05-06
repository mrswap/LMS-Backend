<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            /*
            |--------------------------------------------------------------------------
            | LANGUAGES
            |--------------------------------------------------------------------------
            */
            ['module' => 'languages', 'name' => 'languages.view', 'label' => 'View Languages'],
            ['module' => 'languages', 'name' => 'languages.create', 'label' => 'Create Languages'],
            ['module' => 'languages', 'name' => 'languages.edit', 'label' => 'Edit Languages'],
            ['module' => 'languages', 'name' => 'languages.delete', 'label' => 'Delete Languages'],
            ['module' => 'languages', 'name' => 'languages.status', 'label' => 'Toggle Language Status'],

            /*
            |--------------------------------------------------------------------------
            | USERS
            |--------------------------------------------------------------------------
            */
            ['module' => 'users', 'name' => 'users.view', 'label' => 'View Users'],
            ['module' => 'users', 'name' => 'users.create', 'label' => 'Create Users'],
            ['module' => 'users', 'name' => 'users.edit', 'label' => 'Edit Users'],
            ['module' => 'users', 'name' => 'users.delete', 'label' => 'Delete Users'],
            ['module' => 'users', 'name' => 'users.status', 'label' => 'Toggle User Status'],
            ['module' => 'users', 'name' => 'users.reset-device', 'label' => 'Reset User Device'],

            /*
            |--------------------------------------------------------------------------
            | PROGRAMS
            |--------------------------------------------------------------------------
            */
            ['module' => 'programs', 'name' => 'programs.view', 'label' => 'View Programs'],
            ['module' => 'programs', 'name' => 'programs.create', 'label' => 'Create Programs'],
            ['module' => 'programs', 'name' => 'programs.edit', 'label' => 'Edit Programs'],
            ['module' => 'programs', 'name' => 'programs.delete', 'label' => 'Delete Programs'],
            ['module' => 'programs', 'name' => 'programs.status', 'label' => 'Toggle Program Status'],

            /*
            |--------------------------------------------------------------------------
            | LEVELS
            |--------------------------------------------------------------------------
            */
            ['module' => 'levels', 'name' => 'levels.view', 'label' => 'View Levels'],
            ['module' => 'levels', 'name' => 'levels.create', 'label' => 'Create Levels'],
            ['module' => 'levels', 'name' => 'levels.edit', 'label' => 'Edit Levels'],
            ['module' => 'levels', 'name' => 'levels.delete', 'label' => 'Delete Levels'],
            ['module' => 'levels', 'name' => 'levels.status', 'label' => 'Toggle Level Status'],

            /*
            |--------------------------------------------------------------------------
            | MODULES
            |--------------------------------------------------------------------------
            */
            ['module' => 'modules', 'name' => 'modules.view', 'label' => 'View Modules'],
            ['module' => 'modules', 'name' => 'modules.create', 'label' => 'Create Modules'],
            ['module' => 'modules', 'name' => 'modules.edit', 'label' => 'Edit Modules'],
            ['module' => 'modules', 'name' => 'modules.delete', 'label' => 'Delete Modules'],
            ['module' => 'modules', 'name' => 'modules.status', 'label' => 'Toggle Module Status'],

            /*
            |--------------------------------------------------------------------------
            | CHAPTERS
            |--------------------------------------------------------------------------
            */
            ['module' => 'chapters', 'name' => 'chapters.view', 'label' => 'View Chapters'],
            ['module' => 'chapters', 'name' => 'chapters.create', 'label' => 'Create Chapters'],
            ['module' => 'chapters', 'name' => 'chapters.edit', 'label' => 'Edit Chapters'],
            ['module' => 'chapters', 'name' => 'chapters.delete', 'label' => 'Delete Chapters'],
            ['module' => 'chapters', 'name' => 'chapters.status', 'label' => 'Toggle Chapter Status'],

            /*
            |--------------------------------------------------------------------------
            | TOPICS
            |--------------------------------------------------------------------------
            */
            ['module' => 'topics', 'name' => 'topics.view', 'label' => 'View Topics'],
            ['module' => 'topics', 'name' => 'topics.create', 'label' => 'Create Topics'],
            ['module' => 'topics', 'name' => 'topics.edit', 'label' => 'Edit Topics'],
            ['module' => 'topics', 'name' => 'topics.delete', 'label' => 'Delete Topics'],
            ['module' => 'topics', 'name' => 'topics.status', 'label' => 'Toggle Topic Status'],

            /*
            |--------------------------------------------------------------------------
            | FAQS
            |--------------------------------------------------------------------------
            */
            ['module' => 'faqs', 'name' => 'faqs.view', 'label' => 'View FAQs'],
            ['module' => 'faqs', 'name' => 'faqs.create', 'label' => 'Create FAQs'],
            ['module' => 'faqs', 'name' => 'faqs.edit', 'label' => 'Edit FAQs'],
            ['module' => 'faqs', 'name' => 'faqs.delete', 'label' => 'Delete FAQs'],
            ['module' => 'faqs', 'name' => 'faqs.status', 'label' => 'Toggle FAQ Status'],

            /*
            |--------------------------------------------------------------------------
            | MEDIA
            |--------------------------------------------------------------------------
            */
            ['module' => 'media', 'name' => 'media.view', 'label' => 'View Media'],
            ['module' => 'media', 'name' => 'media.create', 'label' => 'Upload Media'],
            ['module' => 'media', 'name' => 'media.edit', 'label' => 'Edit Media'],
            ['module' => 'media', 'name' => 'media.delete', 'label' => 'Delete Media'],
            ['module' => 'media', 'name' => 'media.status', 'label' => 'Toggle Media Status'],

            /*
            |--------------------------------------------------------------------------
            | CONTENT
            |--------------------------------------------------------------------------
            */
            ['module' => 'content', 'name' => 'content.view', 'label' => 'View Content'],
            ['module' => 'content', 'name' => 'content.create', 'label' => 'Create Content'],
            ['module' => 'content', 'name' => 'content.edit', 'label' => 'Edit Content'],
            ['module' => 'content', 'name' => 'content.delete', 'label' => 'Delete Content'],
            ['module' => 'content', 'name' => 'content.status', 'label' => 'Toggle Content Status'],
            ['module' => 'content', 'name' => 'content.reorder', 'label' => 'Reorder Content'],
            ['module' => 'content', 'name' => 'content.bulk-create', 'label' => 'Bulk Create Content'],
            ['module' => 'content', 'name' => 'content.bulk-edit', 'label' => 'Bulk Edit Content'],
            ['module' => 'content', 'name' => 'content.preview', 'label' => 'Preview Content'],

            /*
            |--------------------------------------------------------------------------
            | ASSESSMENTS
            |--------------------------------------------------------------------------
            */
            ['module' => 'assessments', 'name' => 'assessments.view', 'label' => 'View Assessments'],
            ['module' => 'assessments', 'name' => 'assessments.create', 'label' => 'Create Assessments'],
            ['module' => 'assessments', 'name' => 'assessments.edit', 'label' => 'Edit Assessments'],
            ['module' => 'assessments', 'name' => 'assessments.delete', 'label' => 'Delete Assessments'],
            ['module' => 'assessments', 'name' => 'assessments.status', 'label' => 'Toggle Assessment Status'],

            /*
            |--------------------------------------------------------------------------
            | QUESTIONS
            |--------------------------------------------------------------------------
            */
            ['module' => 'questions', 'name' => 'questions.view', 'label' => 'View Questions'],
            ['module' => 'questions', 'name' => 'questions.create', 'label' => 'Create Questions'],
            ['module' => 'questions', 'name' => 'questions.edit', 'label' => 'Edit Questions'],
            ['module' => 'questions', 'name' => 'questions.delete', 'label' => 'Delete Questions'],

            /*
            |--------------------------------------------------------------------------
            | OPTIONS
            |--------------------------------------------------------------------------
            */
            ['module' => 'options', 'name' => 'options.view', 'label' => 'View Options'],
            ['module' => 'options', 'name' => 'options.create', 'label' => 'Create Options'],
            ['module' => 'options', 'name' => 'options.edit', 'label' => 'Edit Options'],
            ['module' => 'options', 'name' => 'options.delete', 'label' => 'Delete Options'],

            /*
            |--------------------------------------------------------------------------
            | FEEDBACKS
            |--------------------------------------------------------------------------
            */
            ['module' => 'feedbacks', 'name' => 'feedbacks.view', 'label' => 'View Assessment Feedbacks'],

            /*
            |--------------------------------------------------------------------------
            | ROLES
            |--------------------------------------------------------------------------
            */
            ['module' => 'roles', 'name' => 'roles.view', 'label' => 'View Roles'],
            ['module' => 'roles', 'name' => 'roles.create', 'label' => 'Create Roles'],
            ['module' => 'roles', 'name' => 'roles.edit', 'label' => 'Edit Roles'],
            ['module' => 'roles', 'name' => 'roles.delete', 'label' => 'Delete Roles'],
            ['module' => 'roles', 'name' => 'roles.status', 'label' => 'Toggle Role Status'],

            /*
            |--------------------------------------------------------------------------
            | DESIGNATIONS
            |--------------------------------------------------------------------------
            */
            ['module' => 'designations', 'name' => 'designations.view', 'label' => 'View Designations'],
            ['module' => 'designations', 'name' => 'designations.create', 'label' => 'Create Designations'],
            ['module' => 'designations', 'name' => 'designations.edit', 'label' => 'Edit Designations'],
            ['module' => 'designations', 'name' => 'designations.delete', 'label' => 'Delete Designations'],
            ['module' => 'designations', 'name' => 'designations.status', 'label' => 'Toggle Designation Status'],

            /*
            |--------------------------------------------------------------------------
            | SMTP
            |--------------------------------------------------------------------------
            */
            ['module' => 'smtp', 'name' => 'smtp.view', 'label' => 'View SMTP Settings'],
            ['module' => 'smtp', 'name' => 'smtp.edit', 'label' => 'Edit SMTP Settings'],
            ['module' => 'smtp', 'name' => 'smtp.test', 'label' => 'Test SMTP'],

            /*
            |--------------------------------------------------------------------------
            | SITE SETTINGS
            |--------------------------------------------------------------------------
            */
            ['module' => 'site-settings', 'name' => 'site-settings.view', 'label' => 'View Site Settings'],
            ['module' => 'site-settings', 'name' => 'site-settings.edit', 'label' => 'Edit Site Settings'],
            ['module' => 'site-settings', 'name' => 'site-settings.firebase', 'label' => 'Manage Firebase Config'],

            /*
            |--------------------------------------------------------------------------
            | CERTIFICATE SETTINGS
            |--------------------------------------------------------------------------
            */
            ['module' => 'certificate-settings', 'name' => 'certificate-settings.view', 'label' => 'View Certificate Settings'],
            ['module' => 'certificate-settings', 'name' => 'certificate-settings.edit', 'label' => 'Edit Certificate Settings'],

            /*
            |--------------------------------------------------------------------------
            | CONTACTS
            |--------------------------------------------------------------------------
            */
            ['module' => 'contacts', 'name' => 'contacts.view', 'label' => 'View Contacts'],
            ['module' => 'contacts', 'name' => 'contacts.mark-seen', 'label' => 'Mark Contact Seen'],
            ['module' => 'contacts', 'name' => 'contacts.mark-unseen', 'label' => 'Mark Contact Unseen'],

            /*
            |--------------------------------------------------------------------------
            | REPORTS
            |--------------------------------------------------------------------------
            */
            ['module' => 'reports', 'name' => 'reports.audit', 'label' => 'View Audit Reports'],
            ['module' => 'reports', 'name' => 'reports.progress', 'label' => 'View User Progress Reports'],
            ['module' => 'reports', 'name' => 'reports.assessment', 'label' => 'View Assessment Reports'],
            ['module' => 'reports', 'name' => 'reports.content-status', 'label' => 'View Content Status Reports'],
            ['module' => 'reports', 'name' => 'reports.certifications', 'label' => 'View Certifications'],
        ];

        Permission::insert($permissions);
    }
}
