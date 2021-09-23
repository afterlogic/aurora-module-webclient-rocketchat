<template>
  <div class="full-height full-width">
    <q-scroll-area class="full-height full-width">
      <div class="q-pa-lg ">
        <div class="row q-mb-md">
          <div class="col text-h5" v-t="'ROCKETCHATWEBCLIENT.HEADING_SETTINGS_TAB'"></div>
        </div>
        <q-card flat bordered class="card-edit-settings">
          <q-card-section>
            <div class="row q-mb-md">
              <div class="col-2 q-mt-sm" v-t="'ROCKETCHATWEBCLIENT.ADMIN_CHAT_URL_LABEL'"></div>
              <div class="col-5">
                <q-input outlined dense bg-color="white" v-model="chatUrl"/>
              </div>
            </div>
            <div class="row q-mb-md">
              <div class="col-2 q-mt-sm" v-t="'ROCKETCHATWEBCLIENT.ADMIN_USERNAME_LABEL'"></div>
              <div class="col-5">
                <q-input outlined dense bg-color="white" v-model="adminUsername"/>
              </div>
            </div>
            <div class="row q-mb-md">
              <div class="col-2 q-mt-sm" v-t="'ROCKETCHATWEBCLIENT.ADMIN_PASSWORD_LABEL'"></div>
              <div class="col-5">
                <q-input outlined dense bg-color="white" type="password" autocomplete="new-password" v-model="adminPassword"/>
              </div>
            </div>
            <div class="row q-mb-sm">
              <div class="col-2 q-my-sm"></div>
              <div class="col-10">
                <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_CONFIGS_NEEDED_VALUES'" />
                <ul>
                  <li><q-item-label caption>Accounts->Registration->Password Reset: disabled</q-item-label></li>
                  <li><q-item-label caption>General->Restrict access inside any Iframe: disabled</q-item-label></li>
                  <li><q-item-label caption>General->Iframe Integration->Enable Send: enabled</q-item-label></li>
                  <li><q-item-label caption>General->Iframe Integration->Enable Receive: enabled</q-item-label></li>
                  <li><q-item-label caption>Rate Limiter->API Rate Limiter->Enable Rate Limiter: disabled</q-item-label></li>
                </ul>
              </div>
            </div>
            <div class="row q-mb-sm" v-if="!configsRequestIsInProgress">
              <div class="col-2 q-my-sm"></div>
              <div class="col-10">
                <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_CONFIGS_CORRECT'" v-if="configsAreCorrect" />
                <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_APPLY_CONFIGS'" v-if="!configsAreCorrect" />
              </div>
            </div>
            <div class="row q-mb-md">
              <div class="col-2 q-my-sm"></div>
              <div class="col-5">
                <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary"
                       :loading="applyConfigsInProgress" :label="$t('ROCKETCHATWEBCLIENT.ACTION_APPLY_CONFIGS')"
                       @click="applyConfigs" :disable="configsRequestIsInProgress || configsAreCorrect">
                </q-btn>
              </div>
            </div>
            <div class="row q-mb-sm">
              <div class="col-2 q-my-sm"></div>
              <div class="col-10">
                <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_APPLY_TEXTS'"/>
              </div>
            </div>
            <div class="row q-mb-md">
              <div class="col-2 q-my-sm"></div>
              <div class="col-5">
                <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary"
                       :loading="applyTextsInProgress" :label="$t('ROCKETCHATWEBCLIENT.ACTION_APPLY_TEXTS')"
                       @click="applyTexts">
                </q-btn>
              </div>
            </div>
            <div class="row q-mb-sm">
              <div class="col-2 q-my-sm"></div>
              <div class="col-10">
                <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_APPLY_CSS'"/>
              </div>
            </div>
            <div class="row">
              <div class="col-2 q-my-sm"></div>
              <div class="col-5">
                <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary"
                       :loading="applyCssInProgress" :label="$t('ROCKETCHATWEBCLIENT.ACTION_APPLY_CSS')"
                       @click="applyCss">
                </q-btn>
              </div>
            </div>
          </q-card-section>
        </q-card>
        <div class="q-pt-md text-right">
          <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary"
                 :label="$t('COREWEBCLIENT.ACTION_SAVE')"
                 @click="save"/>
        </div>
      </div>
    </q-scroll-area>
    <q-dialog v-model="showSaveBeforeApplyWarning">
      <q-card>
        <q-card-section >
          {{$t('ROCKETCHATWEBCLIENT.WARNING_SAVE_BEFORE_APPLY')}}
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat  :label="$t('COREWEBCLIENT.ACTION_OK')" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <q-inner-loading style="justify-content: flex-start;" :showing="saving">
      <q-linear-progress query />
    </q-inner-loading>
  </div>
</template>

<script>
import errors from 'src/utils/errors'
import notification from 'src/utils/notification'
import webApi from 'src/utils/web-api'

import settings from '../../../RocketChatWebclient/vue/settings'

const FAKE_PASS = '      '

export default {
  name: 'RocketChatWebclientAdminSettings',

  data () {
    return {
      chatUrl: '',
      adminUsername: '',
      adminPassword: FAKE_PASS,
      savedPassword: FAKE_PASS,
      saving: false,

      configsRequestIsInProgress: true,
      configsAreCorrect: false,
      showSaveBeforeApplyWarning: false,
      applyConfigsInProgress: false,
      applyTextsInProgress: false,
      applyCssInProgress: false,
    }
  },

  mounted () {
    this.saving = false
    this.populate()
    this.getRocketChatSettings()
  },

  beforeRouteLeave (to, from, next) {
    this.doBeforeRouteLeave(to, from, next)
  },

  methods: {
    /**
     * Method is used in doBeforeRouteLeave mixin
     */
    hasChanges () {
      const data = settings.getRocketChatWebclientSettings()
      return this.chatUrl !== data.chatUrl ||
          this.adminUsername !== data.adminUsername ||
          this.adminPassword !== this.savedPassword
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
      const data = settings.getRocketChatWebclientSettings()
      this.chatUrl = data.chatUrl
      this.adminUsername = data.adminUsername
      this.adminPassword = FAKE_PASS
      this.savedPassword = FAKE_PASS
    },

    save () {
      if (!this.saving) {
        this.saving = true
        const parameters = {
          ChatUrl: this.chatUrl,
          AdminUsername: this.adminUsername,
        }
        if (FAKE_PASS !== this.adminPassword) {
          parameters.AdminPassword = this.adminPassword
        }
        webApi.sendRequest({
          moduleName: 'RocketChatWebclient',
          methodName: 'UpdateSettings',
          parameters,
        }).then(result => {
          this.saving = false
          if (result === true) {
            settings.saveRocketChatWebclientSettings({
              chatUrl: parameters.ChatUrl,
              adminUsername: parameters.AdminUsername,
            })
            this.populate()
            notification.showReport(this.$t('COREWEBCLIENT.REPORT_SETTINGS_UPDATE_SUCCESS'))
          } else {
            notification.showError(this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED'))
          }
        }, response => {
          this.saving = false
          notification.showError(errors.getTextFromResponse(response, this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED')))
        })
      }
    },

    getRocketChatSettings () {
      this.configsRequestIsInProgress = true
      webApi.sendRequest({
        moduleName: 'RocketChatWebclient',
        methodName: 'GetRocketChatSettings',
      }).then(result => {
        this.configsRequestIsInProgress = false

        const
          accountsPasswordResetDisabled = result.Accounts_PasswordReset === '0' || result.Accounts_PasswordReset === false,
          iframeRestrictAccessDisabled = result.Iframe_Restrict_Access === '0' || result.Iframe_Restrict_Access === false,
          iframeIntegrationSendEnabled = result.Iframe_Integration_send_enable === '1' || result.Iframe_Integration_send_enable === true,
          iframeIntegrationReceiveEnabled = result.Iframe_Integration_receive_enable === '1' || result.Iframe_Integration_receive_enable === true,
          apiEnableRateLimiterDisabled = result.API_Enable_Rate_Limiter === '0' || result.API_Enable_Rate_Limiter === false

        if (accountsPasswordResetDisabled && iframeRestrictAccessDisabled && iframeIntegrationSendEnabled && iframeIntegrationReceiveEnabled && apiEnableRateLimiterDisabled) {
          this.configsAreCorrect = true
        } else {
          this.configsAreCorrect = false
        }
      }, response => {
        this.configsRequestIsInProgress = false
      })
    },

    applyConfigs () {
      if (this.applyConfigsInProgress) {
        return
      }
      if (this.hasChanges()) {
        this.showSaveBeforeApplyWarning = true
        return
      }

      this.applyConfigsInProgress = true
      webApi.sendRequest({
        moduleName: 'RocketChatWebclient',
        methodName: 'ApplyRocketChatConfigs',
      }).then(result => {
        this.applyConfigsInProgress = false
        if (result === true) {
          this.configsAreCorrect = true
          notification.showReport(this.$t('ROCKETCHATWEBCLIENT.REPORT_APPLY_CONFIGS_SUCCESS'))
        } else {
          notification.showError(this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS'))
        }
      }, response => {
        this.applyConfigsInProgress = false
        notification.showError(errors.getTextFromResponse(response, this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS')))
      })
    },

    applyTexts () {
      if (this.applyConfigsInProgress) {
        return
      }
      if (this.hasChanges()) {
        this.showSaveBeforeApplyWarning = true
        return
      }

      this.applyTextsInProgress = true
      webApi.sendRequest({
        moduleName: 'RocketChatWebclient',
        methodName: 'ApplyRocketChatTexts',
      }).then(result => {
        this.applyTextsInProgress = false
        if (result === true) {
          notification.showReport(this.$t('ROCKETCHATWEBCLIENT.REPORT_APPLY_TEXTS_SUCCESS'))
        } else {
          notification.showError(this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_TEXTS'))
        }
      }, response => {
        this.applyTextsInProgress = false
        notification.showError(errors.getTextFromResponse(response, this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_TEXTS')))
      })
    },

    applyCss () {
      if (this.applyConfigsInProgress) {
        return
      }
      if (this.hasChanges()) {
        this.showSaveBeforeApplyWarning = true
        return
      }

      this.applyCssInProgress = true
      webApi.sendRequest({
        moduleName: 'RocketChatWebclient',
        methodName: 'ApplyRocketChatCss',
      }).then(result => {
        this.applyCssInProgress = false
        if (result === true) {
          notification.showReport(this.$t('ROCKETCHATWEBCLIENT.REPORT_APPLY_CSS_SUCCESS'))
        } else {
          notification.showError(this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CSS'))
        }
      }, response => {
        this.applyCssInProgress = false
        notification.showError(errors.getTextFromResponse(response, this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CSS')))
      })
    },
  }
}
</script>

<style scoped>

</style>
