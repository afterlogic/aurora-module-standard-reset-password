<template>
  <q-scroll-area class="full-height full-width">
    <div class="q-pa-md">
      <div class="row q-mb-md">
        <div class="col text-h5">{{ $t('STANDARDRESETPASSWORD.HEADING_SETTINGS_TAB') }}</div>
      </div>
      <q-card flat bordered class="card-edit-settings">
        <q-card-section>
          <div class="row q-ml-sm">
            <div class="col-3 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_RECOVERY_LINK_LIFETIME_MINUTES') }}</div>
            <div class="col-5">
              <q-input outlined dense class="bg-white" v-model="recoveryLinkLifetimeMinutes"/>
            </div>
          </div>
          <div class="row q-ml-sm q-my-md">
              <q-item-label caption>
                <span class="" v-t="'STANDARDRESETPASSWORD.HINT_RECOVERY_LINK_LIFETIME'" />
              </q-item-label>
          </div>
          <div class="row q-ml-sm q-mt-sm">
            <div class="col-3 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_EMAIL') }}</div>
            <div class="col-5">
              <q-input outlined dense class="bg-white" v-model="notificationEmail"/>
            </div>
          </div>
          <div class="row q-ml-sm q-mt-sm">
            <div class="col-3 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE') }}</div>
            <div class="col-5">
              <q-select flat
                        outlined
                        dense class="bg-white" v-model="notificationType"
                        :options="notificationTypes"/>
            </div>
          </div>
          <div class="row q-ml-sm q-my-md">
            <q-item-label caption>
              <span>  {{ inscription }}</span>
            </q-item-label>
          </div>
          <div class="row q-mt-sm q-ml-sm" v-if="notificationType.value === 'smtp'">
            <div class="col-3 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_HOST') }}</div>
            <div class="col-5">
              <q-input outlined dense class="bg-white" v-model="notificationHost"/>
            </div>
              <div class="row">
                <div class="col-2 q-ma-sm text-right" v-t="'MAILWEBCLIENT.LABEL_PORT'" />
                <div class="col-2">
                  <q-input outlined dense class="bg-white" v-model="notificationPort"/>
                </div>
                <div class="col-2 q-pb-md  q-ml-sm">
                  <q-checkbox v-model="notificationUseSsl" color="teal">
                    <q-item-label>{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_USE_SSL') }}</q-item-label>
                  </q-checkbox>
                </div>
              </div>
          </div>
          <div class="row q-mt-sm" v-if="notificationType.value === 'smtp'">
            <div class="col-3">
              <q-checkbox v-model="notificationUseAuth" color="teal">
                  <q-item-label>{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_USE_AUTH') }}</q-item-label>
              </q-checkbox>
            </div>
            <div class="col-2 q-ml-sm">
              <q-input outlined dense class="bg-white" :placeholder="$t('COREWEBCLIENT.LABEL_LOGIN')"
                       :disable="!notificationUseAuth" v-model="notificationLogin"/>
            </div>
            <div class="col-2 q-ml-sm">
              <q-input outlined dense class="bg-white" :placeholder="$t('COREWEBCLIENT.LABEL_PASSWORD')"
                       ref="oldPassword" type="password"
                       :disable="!notificationUseAuth" v-model="notificationPassword"/>
            </div>
          </div>
        </q-card-section>
      </q-card>
      <div class="q-pt-md text-right">
        <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary"
               :label="saving ? $t('COREWEBCLIENT.ACTION_SAVE_IN_PROGRESS') : $t('COREWEBCLIENT.ACTION_SAVE')"
               @click="save"/>
      </div>
    </div>
    <UnsavedChangesDialog ref="unsavedChangesDialog"/>
  </q-scroll-area>
</template>

<script>

import UnsavedChangesDialog from 'src/components/UnsavedChangesDialog'
import settings from '../../../StandardResetPassword/vue/settings'
import webApi from '../../../AdminPanelWebclient/vue/src/utils/web-api'
import notification from '../../../AdminPanelWebclient/vue/src/utils/notification'
import errors from '../../../AdminPanelWebclient/vue/src/utils/errors'
import _ from 'lodash'

export default {
  name: 'PasswordResetSettings',
  components: {
    UnsavedChangesDialog
  },
  data () {
    return {
      saving: false,
      notificationTypes: [],
      notificationType: {},
      inscription: '',
      recoveryLinkLifetimeMinutes: 0,
      notificationEmail: '',
      notificationPort: 0,
      notificationHost: '',
      notificationUseSsl: false,
      notificationUseAuth: false,
      notificationLogin: '',
      notificationPassword: '',
      fakePass: '     ',
      hasNotificationPassword: false
    }
  },
  beforeRouteLeave (to, from, next) {
    if (this.hasChanges() && _.isFunction(this?.$refs?.unsavedChangesDialog?.openConfirmDiscardChangesDialog)) {
      this.$refs.unsavedChangesDialog.openConfirmDiscardChangesDialog(next)
    } else {
      next()
    }
  },
  mounted () {
    this.populate()
  },
  watch: {
    'notificationType.value': function () {
      this.setInscription()
    },
    notificationUseSsl (val) {
      if (val && this.notificationPort === 25) {
        this.notificationPort = 465
      } else if (!val && this.notificationPort === 465) {
        this.notificationPort = 25
      }
    }
  },
  methods: {
    hasChanges () {
      const data = settings.getStandardResetPasswordSettings()
      return this.recoveryLinkLifetimeMinutes !== data.RecoveryLinkLifetimeMinutes ||
          this.notificationEmail !== data.NotificationEmail ||
          this.notificationPort !== data.NotificationPort ||
          this.notificationHost !== data.NotificationHost ||
          this.notificationUseSsl !== data.NotificationUseSsl ||
          this.notificationUseAuth !== data.NotificationUseAuth ||
          this.notificationLogin !== data.NotificationLogin ||
          this.hasNotificationPassword !== data.HasNotificationPassword ||
          this.notificationType.value !== data.NotificationType
    },
    populate () {
      const data = settings.getStandardResetPasswordSettings()
      this.recoveryLinkLifetimeMinutes = data.RecoveryLinkLifetimeMinutes
      this.notificationEmail = data.NotificationEmail
      this.notificationPort = data.NotificationPort
      this.notificationHost = data.NotificationHost
      this.notificationUseSsl = data.NotificationUseSsl
      this.notificationUseAuth = data.NotificationUseAuth
      this.notificationLogin = data.NotificationLogin
      this.hasNotificationPassword = data.HasNotificationPassword
      this.notificationPassword = this.hasNotificationPassword ? this.fakePass : ''
      const notificationType = data.NotificationType
      this.notificationTypes = [
        { value: 'mail', label: this.$t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE_MAIL') },
        { value: 'smtp', label: this.$t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE_SMTP') },
        { value: 'account', label: this.$t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE_ACCOUNT') },
      ]
      this.notificationTypes.forEach((type) => {
        if (type.value === notificationType) {
          this.notificationType = type
        }
      })
      this.setInscription()
    },
    save () {
      this.saving = true
      const parameters = {
        NotificationEmail: this.notificationEmail,
        NotificationType: this.notificationType.value,
        RecoveryLinkLifetimeMinutes: this.recoveryLinkLifetimeMinutes
      }
      if (this.notificationType.value === 'smtp') {
        parameters.NotificationHost = this.notificationHost
        parameters.NotificationPort = this.notificationPort
        parameters.NotificationUseSsl = this.notificationUseSsl
        parameters.NotificationUseAuth = this.notificationUseAuth
        if (this.notificationUseAuth) {
          parameters.NotificationLogin = this.notificationLogin
          if (this.notificationPassword !== this.fakePass) {
            parameters.NotificationPassword = this.notificationPassword
          }
        } else {
          parameters.NotificationUseAuth = this.notificationUseAuth
        }
      }
      webApi.sendRequest({
        moduleName: 'StandardResetPassword',
        methodName: 'UpdateAdminSettings',
        parameters: parameters,
      }).then(result => {
        this.saving = false
        if (result) {
          settings.saveStandardResetPasswordSettings({
            NotificationHost: this.notificationHost,
            NotificationPort: this.notificationPort,
            NotificationUseSsl: this.notificationUseSsl,
            NotificationUseAuth: this.notificationUseAuth,
            NotificationLogin: this.notificationLogin,
            HasNotificationPassword: this.notificationPassword !== '' && this.notificationUseAuth,
            NotificationEmail: this.notificationEmail,
            NotificationType: this.notificationType.value,
            RecoveryLinkLifetimeMinutes: this.recoveryLinkLifetimeMinutes
          })
          notification.showReport(this.$t('COREWEBCLIENT.REPORT_SETTINGS_UPDATE_SUCCESS'))
          this.populate()
        } else {
          notification.showError(this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED'))
        }
      }, response => {
        this.saving = false
        notification.showError(errors.getTextFromResponse(response, this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED')))
      })
    },
    setInscription () {
      switch (this.notificationType?.value) {
        case 'smtp':
          this.inscription = this.$t('STANDARDRESETPASSWORD.HINT_NOTIFICATION_TYPE_SMTP')
          break
        case 'mail':
          this.inscription = this.$t('STANDARDRESETPASSWORD.HINT_NOTIFICATION_TYPE_MAIL')
          break
        case 'account':
          this.inscription = this.$t('STANDARDRESETPASSWORD.HINT_NOTIFICATION_TYPE_ACCOUNT')
          break
      }
    }
  }
}
</script>

<style scoped>

</style>
