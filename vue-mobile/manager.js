import settings from './settings'

export default {
  moduleName: 'StandardResetPassword',

  requiredModules: [],

  init(appData) {
    settings.init(appData)
  },

  getAnonymousPages() {
    const hashModuleName = settings.getSetting('hashModuleName') || 'reset-password'
    return [
      {
        pageName: 'reset-password',
        pagePath: `/${hashModuleName}/:hash?`,
        pageComponent: () => import('./pages/ResetPassword'),
      },
    ]
  },
}
