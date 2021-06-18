import settings from '../../StandardResetPassword/vue/settings'

export default {
  moduleName: 'StandardResetPassword',

  requiredModules: [],

  init(appData) {
    settings.init(appData)
  },

  getAdminSystemTabs() {
    return [
      {
        tabName: 'reset-password',
        title: 'STANDARDRESETPASSWORD.LABEL_SETTINGS_TAB',
        component() {
          return import('src/../../../StandardResetPassword/vue/components/PasswordResetSettings')
        },
      },
    ]
  },
}
