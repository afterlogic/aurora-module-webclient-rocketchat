<template>
  <div class="q-px-lg q-pb-lg q-pt-none">
    <div class="row q-mb-md">
      <div class="col text-h5" v-t="'ROCKETCHATWEBCLIENT.HEADING_SYSTEM_SETTINGS_TAB'"></div>
    </div>
    <q-card flat bordered class="card-edit-settings">
      <q-card-section>
        <div class="row q-mb-sm">
          <div class="col-10">
            <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_CONFIGS_NEEDED_VALUES'" />
            <ul>
              <li>
                <q-item-label caption>
                  Accounts->Registration->Password Reset:<span v-t="'ROCKETCHATWEBCLIENT.LABEL_STATE_DISABLED'" />
                </q-item-label>
              </li>
              <li>
                <q-item-label caption> Accounts->Registration->Registration Form: "Disabled" </q-item-label>
              </li>
              <li>
                <q-item-label caption>
                  General->Restrict access inside any Iframe:
                  <span v-t="'ROCKETCHATWEBCLIENT.LABEL_STATE_DISABLED'" />
                </q-item-label>
              </li>
              <li>
                <q-item-label caption>
                  General->Iframe Integration->Enable Send:<span v-t="'ROCKETCHATWEBCLIENT.LABEL_STATE_ENABLED'" />
                </q-item-label>
              </li>
              <li>
                <q-item-label caption>
                  General->Iframe Integration->Enable Receive:
                  <span v-t="'ROCKETCHATWEBCLIENT.LABEL_STATE_ENABLED'" />
                </q-item-label>
              </li>
              <li>
                <q-item-label caption>
                  Rate Limiter->API Rate Limiter->Enable Rate Limiter:
                  <span v-t="'ROCKETCHATWEBCLIENT.LABEL_STATE_DISABLED'" />
                </q-item-label>
              </li>
            </ul>
          </div>
        </div>
        <div class="row q-mb-sm" v-if="!configsRequestIsInProgress">
          <div class="col-10">
            <q-item-label caption v-t="'ROCKETCHATWEBCLIENT.HINT_CONFIGS_CORRECT'" v-if="configsAreCorrect" />
            <q-item-label
              caption
              v-t="'ROCKETCHATWEBCLIENT.HINT_CONFIGS_INCORRECT'"
              class="text-red"
              v-if="!configsAreCorrect"
            />
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
              :loading="applyRequiredChangesInProgress"
              :label="$t('ROCKETCHATWEBCLIENT.ACTION_APPLY_CONFIGS')"
              @click="applyRequiredChanges"
              :disable="configsRequestIsInProgress || configsAreCorrect"
            >
            </q-btn>
          </div>
        </div>
      </q-card-section>
    </q-card>
  </div>
</template>

<script>
import errors from 'src/utils/errors'
import notification from 'src/utils/notification'
import webApi from 'src/utils/web-api'

export default {
  name: 'SystemSettings',

  props: {
    tenantId: { type: Number, default: null },
    hasChanges: { type: Function },
  },

  data() {
    return {
      configsRequestIsInProgress: true,
      configsAreCorrect: false,
      applyRequiredChangesInProgress: false,
    }
  },

  mounted() {
    this.getRocketChatSettings()
  },

  methods: {
    getRocketChatSettings() {
      this.configsRequestIsInProgress = true
      const parameters = {}
      if (this.tenantId !== null) {
        parameters.TenantId = this.tenantId
      }
      webApi
        .sendRequest({
          moduleName: 'RocketChatWebclient',
          methodName: 'GetRocketChatSettings',
          parameters,
        })
        .then(
          (result) => {
            this.configsRequestIsInProgress = false

            const accountsPasswordResetDisabled =
                result.Accounts_PasswordReset === '0' || result.Accounts_PasswordReset === false,
              accountsRegistrationFormDisabled = result.Accounts_RegistrationForm === 'Disabled',
              iframeRestrictAccessDisabled =
                result.Iframe_Restrict_Access === '0' || result.Iframe_Restrict_Access === false,
              iframeIntegrationSendEnabled =
                result.Iframe_Integration_send_enable === '1' || result.Iframe_Integration_send_enable === true,
              iframeIntegrationReceiveEnabled =
                result.Iframe_Integration_receive_enable === '1' || result.Iframe_Integration_receive_enable === true,
              apiEnableRateLimiterDisabled =
                result.API_Enable_Rate_Limiter === '0' || result.API_Enable_Rate_Limiter === false

            if (
              accountsPasswordResetDisabled &&
              accountsRegistrationFormDisabled &&
              iframeRestrictAccessDisabled &&
              iframeIntegrationSendEnabled &&
              iframeIntegrationReceiveEnabled &&
              apiEnableRateLimiterDisabled
            ) {
              this.configsAreCorrect = true
            } else {
              this.configsAreCorrect = false
            }
          },
          (response) => {
            this.configsRequestIsInProgress = false
          }
        )
    },

    applyRequiredChanges() {
      if (this.applyRequiredChangesInProgress) {
        return
      }
      if (this.hasChanges()) {
        this.$emit('attemptToApplyRequiredChanges')
        return
      }

      this.applyRequiredChangesInProgress = true
      const parameters = {}
      if (this.tenantId !== null) {
        parameters.TenantId = this.tenantId
      }
      webApi
        .sendRequest({
          moduleName: 'RocketChatWebclient',
          methodName: 'ApplyRocketChatRequiredChanges',
          parameters,
        })
        .then(
          (result) => {
            this.applyRequiredChangesInProgress = false
            if (result === true) {
              this.configsAreCorrect = true
              notification.showReport(this.$t('ROCKETCHATWEBCLIENT.REPORT_APPLY_CONFIGS_SUCCESS'))
            } else {
              notification.showError(this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS'))
            }
          },
          (response) => {
            this.applyRequiredChangesInProgress = false
            notification.showError(
              errors.getTextFromResponse(response, this.$t('ROCKETCHATWEBCLIENT.ERROR_APPLY_CONFIGS'))
            )
          }
        )
    },
  },
}
</script>

<style scoped></style>
