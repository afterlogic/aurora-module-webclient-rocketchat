<template>
  <div class="full-height full-width">
    <q-scroll-area class="full-height full-width">
      <div class="q-pa-lg">
        <div class="row q-mb-md">
          <div class="col text-h5" v-t="'ROCKETCHATWEBCLIENT.HEADING_SETTINGS_TAB'"></div>
        </div>
        <q-card flat bordered class="card-edit-settings">
          <q-card-section>
            <div class="row q-mb-md">
              <div class="col-2 q-mt-sm" v-t="'ROCKETCHATWEBCLIENT.ADMIN_CHAT_URL_LABEL'"></div>
              <div class="col-5">
                <q-input outlined dense bg-color="white" v-model="chatUrl" />
              </div>
            </div>
            <div class="row q-mb-md">
              <div class="col-2 q-mt-sm" v-t="'ROCKETCHATWEBCLIENT.ADMIN_USERNAME_LABEL'"></div>
              <div class="col-5">
                <q-input outlined dense bg-color="white" v-model="adminUsername" />
              </div>
            </div>
            <div class="row">
              <div class="col-2 q-mt-sm" v-t="'ROCKETCHATWEBCLIENT.ADMIN_PASSWORD_LABEL'"></div>
              <div class="col-5">
                <q-input
                  outlined
                  dense
                  bg-color="white"
                  type="password"
                  autocomplete="new-password"
                  v-model="adminPassword"
                />
              </div>
            </div>
          </q-card-section>
        </q-card>
        <div class="q-pt-md text-right">
          <q-btn
            unelevated
            no-caps
            dense
            class="q-px-sm"
            :ripple="false"
            color="primary"
            :label="$t('COREWEBCLIENT.ACTION_SAVE')"
            @click="save"
          />
        </div>
      </div>
      <SystemSettings :hasChanges="hasChanges" @attemptToApplyRequiredChanges="attemptToApplyRequiredChanges" />
      <div class="q-pa-lg">
        <div class="row q-mb-md">
          <div class="col text-h5" v-t="'ROCKETCHATWEBCLIENT.HEADING_APPEARANCE_SETTINGS_TAB'"></div>
        </div>
        <q-card flat bordered class="card-edit-settings">
          <q-card-section>
            <div class="row q-mb-sm">
              <div class="col-10">
                <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_APPLY_TEXTS'" />
              </div>
            </div>
            <div class="row q-mb-md">
              <div class="col-5">
                <q-btn
                  unelevated
                  no-caps
                  dense
                  class="q-px-sm"
                  :ripple="false"
                  color="primary"
                  :loading="applyTextChangesInProgress"
                  :label="$t('ROCKETCHATWEBCLIENT.ACTION_APPLY_TEXTS')"
                  @click="applyTextChanges"
                >
                </q-btn>
              </div>
            </div>
            <div class="row q-mb-sm">
              <div class="col-10">
                <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_APPLY_CSS'" />
              </div>
            </div>
            <div class="row">
              <div class="col-5">
                <q-btn
                  unelevated
                  no-caps
                  dense
                  class="q-px-sm"
                  :ripple="false"
                  color="primary"
                  :loading="applyCssChangesInProgress"
                  :label="$t('ROCKETCHATWEBCLIENT.ACTION_APPLY_CSS')"
                  @click="applyCssChanges"
                >
                </q-btn>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </q-scroll-area>
    <q-dialog v-model="showSaveBeforeApplyWarning">
      <q-card>
        <q-card-section v-t="'ROCKETCHATWEBCLIENT.WARNING_SAVE_BEFORE_APPLY'"></q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="$t('COREWEBCLIENT.ACTION_OK')" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <q-inner-loading style="justify-content: flex-start" :showing="saving">
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

import SystemSettings from './SystemSettings'

export default {
  name: 'RocketChatAdminSettings',

  components: {
    SystemSettings,
  },

  data() {
    return {
      chatUrl: '',
      adminUsername: '',
      adminPassword: FAKE_PASS,
      savedPassword: FAKE_PASS,
      saving: false,

      showSaveBeforeApplyWarning: false,
      applyTextChangesInProgress: false,
      applyCssChangesInProgress: false,
    }
  },

  mounted() {
    this.saving = false
    this.populate()
  },

  beforeRouteLeave(to, from, next) {
    this.$root.doBeforeRouteLeave(to, from, next)
  },

  methods: {
    /**
     * Method is used in doBeforeRouteLeave mixin
     */
    hasChanges() {
      const data = settings.getRocketChatWebclientSettings()
      return (
        this.chatUrl !== data.chatUrl ||
        this.adminUsername !== data.adminUsername ||
        this.adminPassword !== this.savedPassword
      )
    },

    /**
     * Method is used in doBeforeRouteLeave mixin,
     * do not use async methods - just simple and plain reverting of values
     * !! hasChanges method must return true after executing revertChanges method
     */
    revertChanges() {
      this.populate()
    },

    populate() {
      const data = settings.getRocketChatWebclientSettings()
      this.chatUrl = data.chatUrl
      this.adminUsername = data.adminUsername
      this.adminPassword = FAKE_PASS
      this.savedPassword = FAKE_PASS
    },

    save() {
      if (!this.saving) {
        this.saving = true
        const parameters = {
          ChatUrl: this.chatUrl,
          AdminUsername: this.adminUsername,
        }
        if (FAKE_PASS !== this.adminPassword) {
          parameters.AdminPassword = this.adminPassword
        }
        webApi
          .sendRequest({
            moduleName: 'RocketChatWebclient',
            methodName: 'UpdateSettings',
            parameters,
          })
          .then(
            (result) => {
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
            },
            (response) => {
              this.saving = false
              notification.showError(
                errors.getTextFromResponse(response, this.$t('COREWEBCLIENT.ERROR_SAVING_SETTINGS_FAILED'))
              )
            }
          )
      }
    },

    attemptToApplyRequiredChanges() {
      if (this.hasChanges()) {
        this.showSaveBeforeApplyWarning = true
      }
    },

    applyTextChanges() {
      if (this.applyTextChangesInProgress) {
        return
      }
      if (this.hasChanges()) {
        this.showSaveBeforeApplyWarning = true
        return
      }

      this.applyTextChangesInProgress = true
      webApi
        .sendRequest({
          moduleName: 'RocketChatWebclient',
          methodName: 'ApplyRocketChatTextChanges',
        })
        .then(
          (result) => {
            this.applyTextChangesInProgress = false
            if (result === true) {
              notification.showReport(this.$t('ROCKETCHATWEBCLIENT.REPORT_APPLY_CONFIGS_SUCCESS'))
            } else {
              notification.showError(this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS'))
            }
          },
          (response) => {
            this.applyTextChangesInProgress = false
            notification.showError(
              errors.getTextFromResponse(response, this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS'))
            )
          }
        )
    },

    applyCssChanges() {
      if (this.applyCssChangesInProgress) {
        return
      }
      if (this.hasChanges()) {
        this.showSaveBeforeApplyWarning = true
        return
      }

      this.applyCssChangesInProgress = true
      webApi
        .sendRequest({
          moduleName: 'RocketChatWebclient',
          methodName: 'ApplyRocketChatCssChanges',
        })
        .then(
          (result) => {
            this.applyCssChangesInProgress = false
            if (result === true) {
              notification.showReport(this.$t('ROCKETCHATWEBCLIENT.REPORT_APPLY_CONFIGS_SUCCESS'))
            } else {
              notification.showError(this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS'))
            }
          },
          (response) => {
            this.applyCssChangesInProgress = false
            notification.showError(
              errors.getTextFromResponse(response, this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS'))
            )
          }
        )
    },
  },
}
</script>

<style scoped>
li::marker {
  content: '-  ';
}
</style>
