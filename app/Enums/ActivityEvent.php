<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityEvent: string
{
    case PAGE_CREATED = 'page.created';
    case PAGE_UPDATED = 'page.updated';
    case PAGE_PUBLISHED = 'page.published';
    case PAGE_SCHEDULED = 'page.scheduled';
    case PAGE_UNPUBLISHED = 'page.unpublished';
    case PAGE_DUPLICATED = 'page.duplicated';
    case PAGE_DELETED = 'page.deleted';
    case RECORD_CREATED = 'record.created';
    case RECORD_UPDATED = 'record.updated';
    case RECORD_PUBLISHED = 'record.published';
    case RECORD_SCHEDULED = 'record.scheduled';
    case RECORD_UNPUBLISHED = 'record.unpublished';
    case RECORD_DUPLICATED = 'record.duplicated';
    case RECORD_DELETED = 'record.deleted';
    case RECORD_TYPE_CREATED = 'record_type.created';
    case RECORD_TYPE_UPDATED = 'record_type.updated';
    case RECORD_TYPE_DELETED = 'record_type.deleted';
    case CATEGORY_CREATED = 'category.created';
    case CATEGORY_UPDATED = 'category.updated';
    case CATEGORY_DELETED = 'category.deleted';
    case MEDIA_UPLOADED = 'media.uploaded';
    case MEDIA_MADE_PRIVATE = 'media.made_private';
    case MEDIA_MADE_PUBLIC = 'media.made_public';
    case MEDIA_DELETED = 'media.deleted';
    case SETTINGS_UPDATED = 'settings.updated';
    case ROLE_CREATED = 'role.created';
    case ROLE_UPDATED = 'role.updated';
    case ROLE_DELETED = 'role.deleted';
    case REDIRECT_SAVED = 'redirect.saved';
    case REDIRECTS_IMPORTED = 'redirect.imported';
    case REDIRECT_DELETED = 'redirect.deleted';
    case USER_INVITED = 'user.invited';
    case USER_ROLE_CHANGED = 'user.role_changed';
    case USER_ENABLED = 'user.enabled';
    case USER_DISABLED = 'user.disabled';
    case USER_DELETED = 'user.deleted';
    case IMPERSONATION_STARTED = 'user.impersonation_started';
    case IMPERSONATION_STOPPED = 'user.impersonation_stopped';
    case SIGNED_IN = 'auth.signed_in';
    case SIGNED_OUT = 'auth.signed_out';
    case SIGN_IN_FAILED = 'auth.failed';
    case LOCKED_OUT = 'auth.locked_out';
    case TWO_FACTOR_FAILED = 'auth.two_factor_failed';
    case ORDER_FULFILLED = 'order.fulfilled';
    case ORDER_REFUNDED = 'order.refunded';
    case ORDER_CANCELLED = 'order.cancelled';
    case SUBMISSION_DELETED = 'submission.deleted';
    case SITE_IMPORTED = 'system.site_imported';
    case BACKUP_CREATED = 'system.backup_created';
    case BACKUP_FAILED = 'system.backup_failed';
    case BACKUP_DELETED = 'system.backup_deleted';
    case SYSTEM_UPDATED = 'system.updated';

    public function label(): string
    {
        return match ($this) {
            self::PAGE_CREATED => __('Created page'),
            self::PAGE_UPDATED => __('Edited page'),
            self::PAGE_PUBLISHED => __('Published page'),
            self::PAGE_SCHEDULED => __('Scheduled page'),
            self::PAGE_UNPUBLISHED => __('Unpublished page'),
            self::PAGE_DUPLICATED => __('Duplicated page'),
            self::PAGE_DELETED => __('Deleted page'),
            self::RECORD_CREATED => __('Created record'),
            self::RECORD_UPDATED => __('Edited record'),
            self::RECORD_PUBLISHED => __('Published record'),
            self::RECORD_SCHEDULED => __('Scheduled record'),
            self::RECORD_UNPUBLISHED => __('Unpublished record'),
            self::RECORD_DUPLICATED => __('Duplicated record'),
            self::RECORD_DELETED => __('Deleted record'),
            self::RECORD_TYPE_CREATED => __('Created content type'),
            self::RECORD_TYPE_UPDATED => __('Changed content type'),
            self::RECORD_TYPE_DELETED => __('Deleted content type'),
            self::CATEGORY_CREATED => __('Created category'),
            self::CATEGORY_UPDATED => __('Changed category'),
            self::CATEGORY_DELETED => __('Deleted category'),
            self::MEDIA_UPLOADED => __('Uploaded file'),
            self::MEDIA_MADE_PRIVATE => __('Made file private'),
            self::MEDIA_MADE_PUBLIC => __('Made file public'),
            self::MEDIA_DELETED => __('Deleted file'),
            self::SETTINGS_UPDATED => __('Changed settings'),
            self::ROLE_CREATED => __('Created role'),
            self::ROLE_UPDATED => __('Changed role'),
            self::ROLE_DELETED => __('Deleted role'),
            self::REDIRECT_SAVED => __('Saved redirect'),
            self::REDIRECTS_IMPORTED => __('Imported redirects'),
            self::REDIRECT_DELETED => __('Deleted redirect'),
            self::USER_INVITED => __('Invited user'),
            self::USER_ROLE_CHANGED => __('Changed user role'),
            self::USER_ENABLED => __('Enabled account'),
            self::USER_DISABLED => __('Disabled account'),
            self::USER_DELETED => __('Deleted account'),
            self::IMPERSONATION_STARTED => __('Logged in as user'),
            self::IMPERSONATION_STOPPED => __('Returned to the admin'),
            self::SIGNED_IN => __('Signed in'),
            self::SIGNED_OUT => __('Signed out'),
            self::SIGN_IN_FAILED => __('Failed sign-in'),
            self::LOCKED_OUT => __('Locked out'),
            self::TWO_FACTOR_FAILED => __('Wrong two-factor code'),
            self::ORDER_FULFILLED => __('Fulfilled order'),
            self::ORDER_REFUNDED => __('Refunded order'),
            self::ORDER_CANCELLED => __('Cancelled order'),
            self::SUBMISSION_DELETED => __('Deleted message'),
            self::SITE_IMPORTED => __('Imported site'),
            self::BACKUP_CREATED => __('Created backup'),
            self::BACKUP_FAILED => __('Backup failed'),
            self::BACKUP_DELETED => __('Deleted backup'),
            self::SYSTEM_UPDATED => __('Updated Wire-Up'),
        };
    }

    public function area(): ActivityArea
    {
        return match (explode('.', $this->value)[0]) {
            'page', 'record', 'record_type', 'category', 'media' => ActivityArea::CONTENT,
            'user' => ActivityArea::PEOPLE,
            'auth' => ActivityArea::SIGN_INS,
            'settings', 'role', 'redirect' => ActivityArea::SETTINGS,
            'order' => ActivityArea::SHOP,
            'submission' => ActivityArea::INBOX,
            default => ActivityArea::SYSTEM,
        };
    }
}
