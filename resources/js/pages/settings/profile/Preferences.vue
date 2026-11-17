<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { IconDeviceDesktop, IconMoon, IconSun } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import DriveBackupController from '@/actions/App/Http/Controllers/App/Settings/DriveBackupController';
import PreferencesController from '@/actions/App/Http/Controllers/App/Settings/PreferencesController';
import { updateLanguage } from '@/actions/App/Http/Controllers/App/Settings/ProfileController';
import VideoPolicyController from '@/actions/App/Http/Controllers/App/Settings/VideoPolicyController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import LanguageSelect from '@/components/LanguageSelect.vue';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSelect, {
    type SettingsSelectOption,
} from '@/components/settings/SettingsSelect.vue';
import TimezoneSelect from '@/components/TimezoneSelect.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import dayjs from '@/dayjs';
import { activeLocale } from '@/language';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { filled } from '@/lib/helpers';
import {
    applyTheme,
    type DefaultPostAction,
    type Theme,
    type TimeFormat,
    type WeekStart,
} from '@/preferences';
import type { Language, User } from '@/types';
import type { TimezoneOption } from '@/types/posting-schedule';
import type { DriveConnection, VideoPolicy } from '@/types/video-policy';

const props = defineProps<{
    timezones: TimezoneOption[];
    /** Null for a Member: only the account's Owner sees the Video policy. */
    videoPolicy: VideoPolicy | null;
    /** Null for a Member: only the account's Owner sees the Drive connection. */
    driveConnection: DriveConnection | null;
}>();

type PreferenceField =
    | 'theme'
    | 'timezone'
    | 'time_format'
    | 'week_starts_on'
    | 'default_post_action'
    | 'locale'
    | 'video_policy';

const page = usePage();
const user = computed(() => page.props.auth.user as User);

const theme = ref<Theme>(user.value.theme);
const locale = ref<string>(page.props.locale as string);
const timezone = ref<string>(user.value.timezone);
const timeFormat = ref<TimeFormat>(user.value.time_format);
const weekStartsOn = ref<WeekStart>(user.value.week_starts_on);
const defaultPostAction = ref<DefaultPostAction>(
    user.value.default_post_action,
);
const savedField = ref<PreferenceField | null>(null);

const themeOptions: SettingsSelectOption[] = [
    {
        value: 'light',
        labelKey: 'settings.preferences.theme.light',
        icon: IconSun,
    },
    {
        value: 'dark',
        labelKey: 'settings.preferences.theme.dark',
        icon: IconMoon,
    },
    {
        value: 'system',
        labelKey: 'settings.preferences.theme.system',
        icon: IconDeviceDesktop,
    },
];

const languages = computed<Language[]>(
    () => (page.props.languages as Language[] | undefined) ?? [],
);

const timeFormatOptions: SettingsSelectOption[] = [
    {
        value: '12h',
        labelKey: 'settings.preferences.time_format.twelve_hour',
    },
    {
        value: '24h',
        labelKey: 'settings.preferences.time_format.twenty_four_hour',
    },
];

const weekStartOptions = computed<SettingsSelectOption[]>(() =>
    (['sunday', 'monday'] as const).map((value, day) => ({
        value,
        label: dayjs()
            .locale(activeLocale.value.toLowerCase())
            .day(day)
            .format('dddd'),
    })),
);

const defaultPostActionOptions: SettingsSelectOption[] = [
    { value: 'next', labelKey: 'posts.composer.queue.next' },
    { value: 'now', labelKey: 'posts.composer.now' },
    { value: 'custom', labelKey: 'posts.composer.set_date_time' },
    { value: 'top', labelKey: 'posts.composer.queue.top' },
];

const save = (
    field: Exclude<PreferenceField, 'locale' | 'video_policy'>,
    value: string,
): void => {
    savedField.value = null;

    router.patch(
        PreferencesController.update.url(),
        { [field]: value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                savedField.value = field;
            },
        },
    );
};

const changeTheme = (value: string): void => {
    theme.value = value as Theme;
    applyTheme(theme.value);
    save('theme', value);
};

const changeLanguage = (value: string): void => {
    locale.value = value;
    savedField.value = null;

    router.put(
        updateLanguage.url(),
        { locale: value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                savedField.value = 'locale';
            },
        },
    );
};

const changeTimezone = (value: string): void => {
    timezone.value = value;
    save('timezone', value);
};

const changeTimeFormat = (value: string): void => {
    timeFormat.value = value as TimeFormat;
    save('time_format', value);
};

const changeWeekStart = (value: string): void => {
    weekStartsOn.value = value as WeekStart;
    save('week_starts_on', value);
};

const changeDefaultPostAction = (value: string): void => {
    defaultPostAction.value = value as DefaultPostAction;
    save('default_post_action', value);
};

/** The saved Video policy; Keep when the page has none (never for an Owner). */
const savedVideoPolicy = (): VideoPolicy => {
    if (filled(props.videoPolicy)) {
        return props.videoPolicy;
    }

    return 'keep';
};

const selectedVideoPolicy = ref<string>(savedVideoPolicy());

watch(
    () => props.videoPolicy,
    () => {
        selectedVideoPolicy.value = savedVideoPolicy();
    },
);

const showsVideoPolicy = computed(() => filled(props.videoPolicy));

const keepAndDeleteOptions: SettingsSelectOption[] = [
    { value: 'keep', labelKey: 'rxdesk.video_policy.options.keep' },
    { value: 'delete', labelKey: 'rxdesk.video_policy.options.delete' },
];

const backupOption: SettingsSelectOption = {
    value: 'backup',
    labelKey: 'rxdesk.video_policy.options.backup',
};

const isDriveConnected = computed(() => props.driveConnection === 'connected');

/** Backup is offered with a working Drive, and kept visible while it is the saved choice. */
const offersBackup = computed(() => {
    if (isDriveConnected.value) {
        return true;
    }

    return selectedVideoPolicy.value === 'backup';
});

const videoPolicyOptions = computed<SettingsSelectOption[]>(() => {
    if (offersBackup.value) {
        return [...keepAndDeleteOptions, backupOption];
    }

    return keepAndDeleteOptions;
});

const driveStatusKey = computed(() => {
    if (filled(props.driveConnection)) {
        return `rxdesk.drive_backup.status.${props.driveConnection}`;
    }

    return 'rxdesk.drive_backup.status.none';
});

const needsDriveReconnect = computed(
    () => props.driveConnection === 'needs_reconnect',
);

const hasDriveConnection = computed(() => props.driveConnection !== 'none');

const disconnectDriveModal = ref<InstanceType<
    typeof ConfirmDeleteModal
> | null>(null);

const openDisconnectDrive = (): void => {
    disconnectDriveModal.value?.open({
        url: DriveBackupController.destroy.url(),
    });
};

/** A Backup policy goes back to Keep when Drive is disconnected, and the dialog says so. */
const disconnectDriveDescription = (
    translate: (key: string) => string,
): string => {
    if (selectedVideoPolicy.value === 'backup') {
        return translate(
            'rxdesk.drive_backup.disconnect_modal.description_backup',
        );
    }

    return translate('rxdesk.drive_backup.disconnect_modal.description');
};

const videoPolicySaveFailedMessage = (
    errors: Record<string, string>,
): string => {
    if (filled(errors.video_policy)) {
        return errors.video_policy;
    }

    return trans('rxdesk.video_policy.save_failed');
};

const changeVideoPolicy = (value: string): void => {
    const previous = selectedVideoPolicy.value;
    selectedVideoPolicy.value = value;
    savedField.value = null;

    router.patch(
        VideoPolicyController.update.url(),
        { video_policy: value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                savedField.value = 'video_policy';
            },
            onError: (errors) => {
                selectedVideoPolicy.value = previous;
                toast.error(videoPolicySaveFailedMessage(errors));
            },
        },
    );
};
</script>

<template>
    <Head :title="$t('settings.preferences.title')" />

    <SettingsLayout :title="$t('settings.preferences.title')">
        <div class="flex flex-col gap-8" data-testid="preferences-page">
            <SettingsRow
                :title="$t('settings.preferences.theme.heading')"
                :description="$t('settings.preferences.theme.description')"
            >
                <SettingsSelect
                    :model-value="theme"
                    :options="themeOptions"
                    :label="$t('settings.preferences.theme.heading')"
                    testid="preferences-theme"
                    @update:model-value="changeTheme"
                />
            </SettingsRow>

            <Separator />

            <SettingsRow
                :title="$t('settings.preferences.language.heading')"
                :description="$t('settings.preferences.language.description')"
            >
                <LanguageSelect
                    :model-value="locale"
                    :languages="languages"
                    :label="$t('settings.preferences.language.heading')"
                    testid="preferences-language"
                    @update:model-value="changeLanguage"
                />
            </SettingsRow>

            <Separator />

            <SettingsRow
                :title="$t('settings.preferences.timezone.heading')"
                :description="$t('settings.preferences.timezone.description')"
            >
                <TimezoneSelect
                    :model-value="timezone"
                    :options="timezones"
                    testid="preferences-timezone"
                    compact
                    @update:model-value="changeTimezone"
                />
            </SettingsRow>

            <Separator />

            <SettingsRow
                :title="$t('settings.preferences.time_format.heading')"
                :description="
                    $t('settings.preferences.time_format.description')
                "
            >
                <SettingsSelect
                    :model-value="timeFormat"
                    :options="timeFormatOptions"
                    :label="$t('settings.preferences.time_format.heading')"
                    testid="preferences-time-format"
                    @update:model-value="changeTimeFormat"
                />
            </SettingsRow>

            <Separator />

            <SettingsRow
                :title="$t('settings.preferences.week_start.heading')"
                :description="$t('settings.preferences.week_start.description')"
            >
                <SettingsSelect
                    :model-value="weekStartsOn"
                    :options="weekStartOptions"
                    :label="$t('settings.preferences.week_start.heading')"
                    testid="preferences-week-start"
                    @update:model-value="changeWeekStart"
                />
            </SettingsRow>

            <Separator />

            <SettingsRow
                :title="$t('settings.preferences.default_post_action.heading')"
                :description="
                    $t('settings.preferences.default_post_action.description')
                "
            >
                <SettingsSelect
                    :model-value="defaultPostAction"
                    :options="defaultPostActionOptions"
                    :label="
                        $t('settings.preferences.default_post_action.heading')
                    "
                    testid="preferences-default-post-action"
                    @update:model-value="changeDefaultPostAction"
                />
            </SettingsRow>

            <template v-if="showsVideoPolicy">
                <Separator />

                <SettingsRow
                    id="video-policy"
                    :title="$t('rxdesk.video_policy.heading')"
                    :description="$t('rxdesk.video_policy.description')"
                >
                    <SettingsSelect
                        :model-value="selectedVideoPolicy"
                        :options="videoPolicyOptions"
                        :label="$t('rxdesk.video_policy.heading')"
                        testid="preferences-video-policy"
                        @update:model-value="changeVideoPolicy"
                    />
                </SettingsRow>

                <Separator />

                <SettingsRow
                    id="drive-backup"
                    :title="$t('rxdesk.drive_backup.heading')"
                    :description="$t('rxdesk.drive_backup.description')"
                >
                    <div class="flex flex-col items-end gap-2">
                        <p
                            class="text-sm text-muted-foreground"
                            data-testid="preferences-drive-status"
                        >
                            {{ $t(driveStatusKey) }}
                        </p>
                        <div class="flex gap-2">
                            <Button
                                v-if="hasDriveConnection"
                                variant="outline"
                                data-testid="preferences-drive-disconnect"
                                @click="openDisconnectDrive"
                            >
                                {{ $t('rxdesk.drive_backup.disconnect') }}
                            </Button>
                            <Button
                                v-if="!isDriveConnected"
                                as-child
                                data-testid="preferences-drive-connect"
                            >
                                <a :href="DriveBackupController.connect.url()">
                                    <template v-if="needsDriveReconnect">
                                        {{
                                            $t('rxdesk.drive_backup.reconnect')
                                        }}
                                    </template>
                                    <template v-else>
                                        {{ $t('rxdesk.drive_backup.connect') }}
                                    </template>
                                </a>
                            </Button>
                        </div>
                    </div>
                </SettingsRow>
            </template>

            <span
                v-if="savedField"
                :data-testid="`preferences-saved-${savedField}`"
                class="sr-only"
            />

            <ConfirmDeleteModal
                ref="disconnectDriveModal"
                :title="$t('rxdesk.drive_backup.disconnect_modal.title')"
                :description="disconnectDriveDescription($t)"
                :action="$t('rxdesk.drive_backup.disconnect')"
                :cancel="$t('rxdesk.drive_backup.disconnect_modal.cancel')"
                action-test-id="preferences-drive-disconnect-confirm"
            />
        </div>
    </SettingsLayout>
</template>
