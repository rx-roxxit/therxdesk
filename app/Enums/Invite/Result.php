<?php

declare(strict_types=1);

namespace App\Enums\Invite;

enum Result
{
    case WrongEmail;
    case Gone;
    case AlreadyAccepted;
    case AlreadyMember;
    case OwnerHasWorkspaces;
    case Accepted;
    case Declined;

    public function flashBanner(): string
    {
        return match ($this) {
            self::WrongEmail => __('settings.members.flash.wrong_email'),
            self::Gone => __('settings.members.flash.invite_workspace_gone'),
            self::AlreadyAccepted, self::AlreadyMember => __('settings.members.flash.already_member'),
            self::OwnerHasWorkspaces => __('rxdesk.invite.owner_has_workspaces'),
            self::Accepted => __('settings.members.flash.invite_accepted'),
            self::Declined => __('settings.members.flash.invite_declined'),
        };
    }

    /**
     * @return 'danger'|'info'|'success'
     */
    public function flashStyle(): string
    {
        return match ($this) {
            self::WrongEmail, self::Gone, self::OwnerHasWorkspaces => 'danger',
            self::AlreadyAccepted, self::AlreadyMember, self::Declined => 'info',
            self::Accepted => 'success',
        };
    }
}
