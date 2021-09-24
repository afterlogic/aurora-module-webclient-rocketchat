<template>
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
        </q-card-section>
      </q-card>
      <div class="q-pa-md text-right">
        <q-btn unelevated no-caps dense class="q-px-sm" :ripple="false" color="primary"
               :label="$t('COREWEBCLIENT.ACTION_SAVE')"
               @click="save"/>
      </div>
    </div>
    <q-inner-loading style="justify-content: flex-start;" :showing="loading || saving">
      <q-linear-progress query />
    </q-inner-loading>
  </q-scroll-area>
</template>

<script>
import errors from 'src/utils/errors'
import notification from 'src/utils/notification'
import types from 'src/utils/types'
import webApi from 'src/utils/web-api'

const FAKE_PASS = '      '

export default {
  name: 'RocketChatWebclientAdminSettingPerTenant',

  data () {
    return {
      chatUrl: '',
      adminUsername: '',
      adminPassword: FAKE_PASS,
      savedPassword: FAKE_PASS,
      saving: false,
      loading: false,
      tenant: null
    }
  },

  computed: {
    tenantId () {
      return this.$store.getters['tenants/getCurrentTenantId']
    },
  },

  mounted () {
    this.loading = false
    this.saving = false
    this.populate()
  },

  beforeRouteLeave (to, from, next) {
    this.doBeforeRouteLeave(to, from, next)
  },

  methods: {
    /**
     * Method is used in doBeforeRouteLeave mixin
     */
    hasChanges () {
      if (this.loading) {
        return false
      }

      const tenantCompleteData = types.pObject(this.tenant?.completeData)
      return this.chatUrl !== tenantCompleteData['RocketChatWebclient::ChatUrl'] ||
          this.adminUsername !== tenantCompleteData['RocketChatWebclient::AdminUsername'] ||
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
      const tenant = this.$store.getters['tenants/getTenant'](this.tenantId)
      if (tenant) {
        if (tenant.completeData['RocketChatWebclient::ChatUrl'] !== undefined) {
          this.tenant = tenant
          this.chatUrl = tenant.completeData['RocketChatWebclient::ChatUrl']
          this.adminUsername = tenant.completeData['RocketChatWebclient::AdminUsername']
          this.adminPassword = FAKE_PASS
          this.savedPassword = FAKE_PASS
        } else {
          this.getSettings()
        }
      }
    },

    save () {
      if (!this.saving) {
        this.saving = true
        const parameters = {
          ChatUrl: this.chatUrl,
          AdminUsername: this.adminUsername,
          TenantId: this.tenantId
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
            this.savedPassword = this.adminPassword
            const data = {
              'RocketChatWebclient::ChatUrl': parameters.ChatUrl,
              'RocketChatWebclient::AdminUsername': parameters.AdminUsername,
            }
            this.$store.commit('tenants/setTenantCompleteData', { id: this.tenantId, data })
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

    getSettings () {
      this.loading = true
      const parameters = {
        TenantId: this.tenantId
      }
      webApi.sendRequest({
        moduleName: 'RocketChatWebclient',
        methodName: 'GetSettings',
        parameters,
      }).then(result => {
        this.loading = false
        if (result) {
          const data = {
            'RocketChatWebclient::ChatUrl': types.pString(result.ChatUrl),
            'RocketChatWebclient::AdminUsername': types.pString(result.AdminUsername),
          }
          this.$store.commit('tenants/setTenantCompleteData', { id: this.tenantId, data })
        }
      })
    },
  }
}
</script>

<style scoped>

</style>
