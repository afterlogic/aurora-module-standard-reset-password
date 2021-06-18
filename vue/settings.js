import _ from 'lodash'

import typesUtils from 'src/utils/types'

class StandardResetPassword {
  constructor (appData) {
    const standardResetPassword = typesUtils.pObject(appData.StandardResetPassword)
    if (!_.isEmpty(standardResetPassword)) {
      this.RecoveryLinkLifetimeMinutes = typesUtils.pInt(standardResetPassword.RecoveryLinkLifetimeMinutes, this.RecoveryLinkLifetimeMinutes);
      this.NotificationEmail = typesUtils.pString(standardResetPassword.NotificationEmail, this.NotificationEmail);
      this.NotificationType = typesUtils.pString(standardResetPassword.NotificationType, this.NotificationType);
      this.NotificationHost = typesUtils.pString(standardResetPassword.NotificationHost, this.NotificationHost);
      this.NotificationPort = typesUtils.pInt(standardResetPassword.NotificationPort, this.NotificationPort);
      this.NotificationUseSsl = typesUtils.pBool(standardResetPassword.NotificationUseSsl, this.NotificationUseSsl);
      this.NotificationUseAuth = typesUtils.pBool(standardResetPassword.NotificationUseAuth, this.NotificationUseAuth);
      this.NotificationLogin = typesUtils.pString(standardResetPassword.NotificationLogin, this.NotificationLogin);
      this.HasNotificationPassword = typesUtils.pBool(standardResetPassword.HasNotificationPassword, this.HasNotificationPassword);
    }
  }

  saveStandardResetPasswordSettings ({ NotificationEmail, NotificationType, NotificationHost, NotificationPort, NotificationUseSsl, NotificationUseAuth, NotificationLogin, HasNotificationPassword, RecoveryLinkLifetimeMinutes }) {
    this.RecoveryLinkLifetimeMinutes = RecoveryLinkLifetimeMinutes
    this.NotificationEmail = NotificationEmail
    this.NotificationType = NotificationType

    if (NotificationType === 'smtp') {
      this.NotificationHost = NotificationHost
      this.NotificationPort = NotificationPort
      this.NotificationUseSsl = NotificationUseSsl
      this.NotificationUseAuth = NotificationUseAuth
      this.NotificationLogin = NotificationLogin
      this.HasNotificationPassword = HasNotificationPassword
    }
  }
}

let settings = null

export default {
  init (appData) {
    settings = new StandardResetPassword(appData)
  },
  getStandardResetPasswordSettings () {
    return {
      NotificationEmail: settings.NotificationEmail,
      NotificationType: settings.NotificationType,
      NotificationHost: settings.NotificationHost,
      NotificationPort: settings.NotificationPort,
      NotificationUseSsl: settings.NotificationUseSsl,
      NotificationUseAuth: settings.NotificationUseAuth,
      NotificationLogin: settings.NotificationLogin,
      HasNotificationPassword: settings.HasNotificationPassword,
      RecoveryLinkLifetimeMinutes: settings.RecoveryLinkLifetimeMinutes
    }
  },
  saveStandardResetPasswordSettings (appData) {
    settings.saveStandardResetPasswordSettings(appData)
  }
}