<template>
  <q-scroll-area class="full-height full-width">
    <div class="q-pa-lg">
      <div class="row q-mb-md">
        <div class="col text-h5">{{ $t('STANDARDRESETPASSWORD.HEADING_SETTINGS_TAB') }}</div>
      </div>
      <q-card flat bordered class="card-edit-settings">
        <q-card-section>
          <div class="row">
            <div class="col-2 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_RECOVERY_LINK_LIFETIME_MINUTES') }}</div>
            <div class="col-5">
              <q-input outlined dense bg-color="white" v-model="recoveryLinkLifetimeMinutes"/>
            </div>
          </div>
          <div class="row q-my-md">
              <q-item-label caption>
                <span class="" v-t="'STANDARDRESETPASSWORD.HINT_RECOVERY_LINK_LIFETIME'" />
              </q-item-label>
          </div>
          <div class="row q-mt-sm">
            <div class="col-2 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_EMAIL') }}</div>
            <div class="col-5">
              <q-input outlined dense bg-color="white" v-model="notificationEmail"/>
            </div>
          </div>
          <div class="row q-mt-md">
            <div class="col-2 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE') }}</div>
            <div class="col-5">
              <q-select flat
                        outlined
                        dense bg-color="white" v-model="notificationType"
                        :options="notificationTypes"/>
            </div>
          </div>
          <div class="row q-my-md">
            <q-item-label caption>
              <span>  {{ inscription }}</span>
            </q-item-label>
          </div>
          <div class="row q-mt-sm" v-if="notificationType.value === 'smtp'">
            <div class="col-2 q-mt-sm">{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_HOST') }}</div>
            <div class="col-5">
              <q-input outlined dense bg-color="white" v-model="notificationHost"/>
            </div>
            <div class="col-1 q-ma-sm text-right" v-t="'MAILWEBCLIENT.LABEL_PORT'"/>
            <div class="col-1">
              <q-input outlined dense bg-color="white" v-model="notificationPort"/>
            </div>
            <div class="col-1 q-pb-md  q-ml-sm">
              <q-checkbox v-model="notificationUseSsl">
                <q-item-label>{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_USE_SSL') }}</q-item-label>
              </q-checkbox>
            </div>
          </div>
          <div class="row q-mt-sm" v-if="notificationType.value === 'smtp'">
            <div class="col-2 q-mt-sm">
              <q-checkbox dense v-model="notificationUseAuth">
                  <q-item-label>{{ $t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_USE_AUTH') }}</q-item-label>
              </q-checkbox>
            </div>
            <div class="col-2">
              <q-input outlined dense bg-color="white" :placeholder="$t('COREWEBCLIENT.LABEL_LOGIN')"
                       :disable="!notificationUseAuth" v-model="notificationLogin"/>
            </div>
            <div class="col-2 q-ml-sm">
              <!-- fake fields are a workaround to prevent auto-filling and saving passwords in Firefox -->
              <input style="display:none" type="text" name="fakeusernameremembered"/>
              <input style="display:none" type="password" name="fakepasswordremembered"/>
              <q-input outlined dense bg-color="white" :placeholder="$t('COREWEBCLIENT.LABEL_PASSWORD')"
                       type="password" autocomplete="off"
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
  </q-scroll-area>
</template>

<script>
import errors from 'src/utils/errors'
import notification from 'src/utils/notification'
import webApi from 'src/utils/web-api'

import settings from '../settings'

const FAKE_PASS = '     '

export default {
  name: 'PasswordResetSettings',

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
      notificationPassword: FAKE_PASS,
      savedPass: FAKE_PASS,
      hasNotificationPassword: false
    }
  },

  beforeRouteLeave (to, from, next) {
    this.doBeforeRouteLeave(to, from, next)
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
    /**
     * Method is used in doBeforeRouteLeave mixin
     */
    hasChanges () {
      const data = settings.getStandardResetPasswordSettings()
      return this.recoveryLinkLifetimeMinutes !== data.recoveryLinkLifetimeMinutes ||
          this.notificationEmail !== data.notificationEmail ||
          this.notificationPort !== data.notificationPort ||
          this.notificationHost !== data.notificationHost ||
          this.notificationUseSsl !== data.notificationUseSsl ||
          this.notificationUseAuth !== data.notificationUseAuth ||
          this.notificationLogin !== data.notificationLogin ||
          this.hasNotificationPassword !== data.hasNotificationPassword ||
          this.notificationType.value !== data.notificationType
    },

    /**
     * Method is used in doBeforeRouteLeave mixin,
     * do not use async methods - just simple and plain reverting of values
     * !! hasChanges method must return true after executing revertChanges method
     */
    revertChanges () {
      this.populate()
    },

    populate () {
      const data = settings.getStandardResetPasswordSettings()
      this.recoveryLinkLifetimeMinutes = data.recoveryLinkLifetimeMinutes
      this.notificationEmail = data.notificationEmail
      this.notificationPort = data.notificationPort
      this.notificationHost = data.notificationHost
      this.notificationUseSsl = data.notificationUseSsl
      this.notificationUseAuth = data.notificationUseAuth
      this.notificationLogin = data.notificationLogin
      this.hasNotificationPassword = data.hasNotificationPassword
      const notificationType = data.notificationType
      this.notificationTypes = [
        { value: 'mail', label: this.$t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE_MAIL') },
        { value: 'smtp', label: this.$t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE_SMTP') },
        { value: 'account', label: this.$t('STANDARDRESETPASSWORD.LABEL_NOTIFICATION_TYPE_ACCOUNT') },
      ]
      this.notificationType = this.notificationTypes.find(type => type.value === notificationType)
      this.setInscription()
    },
    save () {
      if (!this.saving) {
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
            if (this.notificationPassword !== FAKE_PASS) {
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
              notificationHost: this.notificationHost,
              notificationPort: this.notificationPort,
              notificationUseSsl: this.notificationUseSsl,
              notificationUseAuth: this.notificationUseAuth,
              notificationLogin: this.notificationLogin,
              hasNotificationPassword: this.notificationPassword !== '' && this.notificationUseAuth,
              notificationEmail: this.notificationEmail,
              notificationType: this.notificationType.value,
              recoveryLinkLifetimeMinutes: this.recoveryLinkLifetimeMinutes
            })
            this.savedPass = this.notificationPassword
            notification.showReport(this.$t('COREWEBCLIENT.REPORT_SETTINGS_UPDATE_SUCCESS'))
            this.populate()
          } else {
            notification.showError(this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED'))
          }
        }, response => {
          this.saving = false
          notification.showError(errors.getTextFromResponse(response, this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED')))
        })
      }
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
