<template>
  <LoginLayout :subheading="$t('STANDARDRESETPASSWORD.HEADING_RESET_PASSWORD')">
    <div class="full-width q-my-auto">
      <!-- Step 0: change password via recovery hash -->
      <template v-if="step === 0">
        <p
          v-if="passwordChanged"
          class="text-grey-7"
          v-html="$t('STANDARDRESETPASSWORD.INFO_PASSWORD_CHANGED')"
        />
        <template v-else-if="resetPasswordHashUserPublicId">
          <p
            class="text-grey-7"
            v-html="$t('STANDARDRESETPASSWORD.INFO_RESET_PASSWORD_HASH', {
              USERNAME: resetPasswordHashUserPublicId,
              SITE_NAME: siteName,
            })"
          />
          <q-form class="q-mt-md">
            <q-input
              class="login_input"
              :type="isNewPasswordVisible ? 'text' : 'password'"
              v-model="newPassword"
              :placeholder="$t('COREWEBCLIENT.LABEL_NEW_PASSWORD')"
              autocomplete="new-password"
            >
              <template v-slot:prepend>
                <q-icon name="lock_outline" color="grey-5" />
              </template>
              <template v-slot:append>
                <q-icon
                  :name="isNewPasswordVisible ? 'visibility' : 'visibility_off'"
                  color="grey-5"
                  class="cursor-pointer"
                  @click="isNewPasswordVisible = !isNewPasswordVisible"
                />
              </template>
            </q-input>
            <q-input
              class="login_input"
              :type="isConfirmPasswordVisible ? 'text' : 'password'"
              v-model="confirmPassword"
              :placeholder="$t('COREWEBCLIENT.LABEL_CONFIRM_PASSWORD')"
              autocomplete="new-password"
              @keydown.enter="changePassword"
            >
              <template v-slot:prepend>
                <q-icon name="lock_outline" color="grey-5" />
              </template>
              <template v-slot:append>
                <q-icon
                  :name="isConfirmPasswordVisible ? 'visibility' : 'visibility_off'"
                  color="grey-5"
                  class="cursor-pointer"
                  @click="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </template>
            </q-input>
          </q-form>
        </template>
        <p
          v-else-if="!gettingUserPublicId"
          class="text-grey-7"
        >
          {{ $t('STANDARDRESETPASSWORD.ERROR_LINK_NOT_VALID') }}
        </p>
      </template>

      <!-- Step 1: enter account email -->
      <template v-else-if="step === 1">
        <q-form>
          <q-input
            class="login_input"
            type="email"
            v-model="email"
            :placeholder="$t('STANDARDRESETPASSWORD.LABEL_ACCOUNT_EMAIL')"
            @keydown.enter="continueRecovery"
          >
            <template v-slot:prepend>
              <q-icon name="mail_outline" color="grey-5" />
            </template>
          </q-input>
        </q-form>
      </template>

      <!-- Step 2: confirm sending recovery email -->
      <p
        v-else-if="step === 2"
        class="text-grey-7"
        v-html="recoverThroughEmailText"
      />

      <!-- Step 3: recovery email sent -->
      <p
        v-else-if="step === 3"
        class="text-grey-7"
        v-html="sendRecoveryEmailText"
      />
    </div>

    <div class="q-pb-xl text-center full-width">
      <template v-if="step === 0 && passwordChanged">
        <AppButton
          :label="$t('COREWEBCLIENT.ACTION_SIGN_IN')"
          @click="backToLogin"
        />
      </template>
      <template v-else-if="step === 0 && resetPasswordHashUserPublicId && !passwordChanged">
        <AppButton
          :label="$t('COREWEBCLIENT.ACTION_CHANGE_PASSWORD')"
          :loading="changingPassword"
          :disabled="!newPassword || !confirmPassword"
          @click="changePassword"
        />
      </template>
      <template v-else-if="step === 0 && !gettingUserPublicId && !resetPasswordHashUserPublicId">
        <AppButton
          class="q-mb-md"
          :label="$t('STANDARDRESETPASSWORD.ACTION_RESET_PASSWORD')"
          @click="backToStep1"
        />
        <AppButton
          :label="$t('COREWEBCLIENT.ACTION_SIGN_IN')"
          @click="backToLogin"
        />
      </template>
      <template v-else-if="step === 1">
        <AppButton
          class="q-mb-md"
          :label="$t('STANDARDRESETPASSWORD.ACTION_CONTINUE')"
          :loading="gettingRecoveryEmail"
          :disabled="!email"
          @click="continueRecovery"
        />
        <AppButton
          :label="$t('STANDARDRESETPASSWORD.ACTION_BACK')"
          @click="backToLogin"
        />
      </template>
      <template v-else-if="step === 2">
        <AppButton
          class="q-mb-md"
          :label="$t('COREWEBCLIENT.ACTION_SEND')"
          :loading="sendingRecoveryEmail"
          @click="sendRecoveryEmail"
        />
        <AppButton
          :label="$t('STANDARDRESETPASSWORD.ACTION_BACK')"
          @click="backToStep1"
        />
      </template>
      <template v-else-if="step === 3">
        <AppButton
          :label="$t('COREWEBCLIENT.ACTION_SIGN_IN')"
          @click="backToLogin"
        />
      </template>
    </div>
  </LoginLayout>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { i18n } from 'src/boot/i18n'

import webApi from 'src/api/web-api'
import notification from 'src/utils/notification'
import addressUtils from 'src/utils/address'
import coreSettings from 'src/settings'

import AppButton from 'src/components/common/AppButton'
import LoginLayout from 'src/layouts/LoginLayout'

export default {
  name: 'ResetPassword',

  components: {
    LoginLayout,
    AppButton,
  },

  setup() {
    const route = useRoute()
    const router = useRouter()

    const step = ref(1)
    const email = ref('')
    const newPassword = ref('')
    const confirmPassword = ref('')
    const isNewPasswordVisible = ref(false)
    const isConfirmPasswordVisible = ref(false)

    const gettingUserPublicId = ref(false)
    const resetPasswordHashUserPublicId = ref('')
    const passwordChanged = ref(false)
    const changingPassword = ref(false)

    const gettingRecoveryEmail = ref(false)
    const sendingRecoveryEmail = ref(false)
    const recoverThroughEmailText = ref('')
    const sendRecoveryEmailText = ref('')

    const siteName = computed(() => coreSettings.getSetting('siteName') || '')

    const resetPasswordHash = computed(() => {
      return route.params.hash || ''
    })

    const initFromRoute = async () => {
      const hash = resetPasswordHash.value
      resetPasswordHashUserPublicId.value = ''
      passwordChanged.value = false
      if (hash) {
        step.value = 0
        gettingUserPublicId.value = true
        try {
          const result = await webApi.sendRequest({
            moduleName: 'StandardResetPassword',
            methodName: 'GetUserPublicId',
            parameters: { Hash: hash },
            silentError: true,
          })
          if (result) {
            resetPasswordHashUserPublicId.value = result
          }
        } catch (e) {
          // invalid link — show error state
        }
        gettingUserPublicId.value = false
      } else {
        step.value = 1
      }
    }

    const continueRecovery = async () => {
      const trimmedEmail = email.value.trim()
      if (!trimmedEmail) {
        return
      }
      if (!addressUtils.isCorrectEmail(trimmedEmail)) {
        notification.showError(i18n.global.t('STANDARDRESETPASSWORD.ERROR_INCORRECT_EMAIL'))
        return
      }

      gettingRecoveryEmail.value = true
      try {
        const result = await webApi.sendRequest({
          moduleName: 'StandardResetPassword',
          methodName: 'GetStarredRecoveryEmailAddress',
          parameters: { UserPublicId: trimmedEmail },
          defaultErrorText: i18n.global.t('STANDARDRESETPASSWORD.ERROR_RECOVERY_EMAIL_NOT_FOUND'),
        })
        if (result) {
          step.value = 2
          recoverThroughEmailText.value = i18n.global.t(
            'STANDARDRESETPASSWORD.INFO_EMAIL_RECOVER_SENT',
            {
              USERNAME: trimmedEmail,
              EMAIL: result,
              SITE_NAME: siteName.value,
            }
          )
          sendRecoveryEmailText.value = i18n.global.t(
            'STANDARDRESETPASSWORD.INFO_RECOVERY_LINK_SENT',
            {
              USERNAME: trimmedEmail,
              EMAIL: result,
              SITE_NAME: siteName.value,
            }
          )
        }
      } catch (e) {
        // error already shown by webApi
      }
      gettingRecoveryEmail.value = false
    }

    const sendRecoveryEmail = async () => {
      sendingRecoveryEmail.value = true
      try {
        const result = await webApi.sendRequest({
          moduleName: 'StandardResetPassword',
          methodName: 'SendPasswordResetEmail',
          parameters: { UserPublicId: email.value.trim() },
          defaultErrorText: i18n.global.t('STANDARDRESETPASSWORD.ERROR_RECOVERY_EMAIL_NOT_SENT'),
        })
        if (result) {
          step.value = 3
        }
      } catch (e) {
        // error already shown by webApi
      }
      sendingRecoveryEmail.value = false
    }

    const changePassword = async () => {
      if (!newPassword.value.trim()) {
        return
      }
      if (!confirmPassword.value.trim()) {
        return
      }
      if (newPassword.value !== confirmPassword.value) {
        notification.showError(i18n.global.t('COREWEBCLIENT.ERROR_PASSWORDS_DO_NOT_MATCH'))
        return
      }

      changingPassword.value = true
      passwordChanged.value = false
      try {
        const result = await webApi.sendRequest({
          moduleName: 'StandardResetPassword',
          methodName: 'ChangePassword',
          parameters: {
            Hash: resetPasswordHash.value,
            NewPassword: newPassword.value,
          },
          defaultErrorText: i18n.global.t('STANDARDRESETPASSWORD.ERROR_PASSWORD_CHANGE'),
        })
        if (result === true) {
          passwordChanged.value = true
        }
      } catch (e) {
        // error already shown by webApi
      }
      changingPassword.value = false
    }

    const backToStep1 = () => {
      recoverThroughEmailText.value = ''
      sendRecoveryEmailText.value = ''
      step.value = 1
      if (resetPasswordHash.value) {
        router.replace({ name: 'reset-password' })
      }
    }

    const backToLogin = () => {
      router.replace({ name: 'login' })
    }

    watch(() => route.params.hash, () => {
      initFromRoute()
    })

    onMounted(() => {
      initFromRoute()
    })

    return {
      step,
      email,
      newPassword,
      confirmPassword,
      isNewPasswordVisible,
      isConfirmPasswordVisible,
      gettingUserPublicId,
      resetPasswordHashUserPublicId,
      passwordChanged,
      changingPassword,
      gettingRecoveryEmail,
      sendingRecoveryEmail,
      recoverThroughEmailText,
      sendRecoveryEmailText,
      siteName,
      continueRecovery,
      sendRecoveryEmail,
      changePassword,
      backToStep1,
      backToLogin,
    }
  },
}
</script>

<style lang="scss">
.login_input .q-field__control:after {
  transform: unset;
  opacity: 0;
  transition: opacity 0.3s;
}

.login_input.q-field--highlighted .q-field__control:after {
  opacity: 1;
  transform: unset;
}
</style>
