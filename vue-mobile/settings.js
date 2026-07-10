import _ from 'lodash'

import typesUtils from 'src/utils/types'

class StandardResetPasswordSettings {
  constructor(appData) {
    const data = typesUtils.pObject(appData.StandardResetPassword)
    this.hashModuleName = 'reset-password'
    if (!_.isEmpty(data)) {
      this.hashModuleName = typesUtils.pString(data.HashModuleName, this.hashModuleName)
    }
  }
}

let settings = null

export default {
  init(appData) {
    settings = new StandardResetPasswordSettings(appData)
  },

  getSetting(settingName) {
    return settings ? settings[settingName] : null
  },
}
