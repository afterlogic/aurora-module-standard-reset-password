import settings from "../../StandardResetPassword/vue/settings";

export default {
    name: 'PasswordResetSettings',
    init (appData) {
        settings.init(appData)
    },
    getAdminSystemTabs () {
        return [
            {
                name: 'reset-password',
                title: 'STANDARDRESETPASSWORD.LABEL_SETTINGS_TAB',
                component () {
                    return import('src/../../../StandardResetPassword/vue/components/PasswordResetSettings')
                },
            },
        ]
    },
}
