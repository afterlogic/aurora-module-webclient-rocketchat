import settings from '../../RocketChatWebclient/vue/settings'

export default {
  moduleName: 'RocketChatWebclient',

  requiredModules: [],

  init (appData) {
    settings.init(appData)
  },

  getAdminSystemTabs () {
    return [
      {
        tabName: 'chat',
        title: 'ROCKETCHATWEBCLIENT.ADMIN_SETTINGS_TAB_LABEL',
        component () {
          return import('./components/RocketChatAdminSettings')
        },
      },
    ]
  },

  getAdminTenantTabs () {
    return [
      {
        tabName: 'chat',
        paths: [
          'id/:id/chat',
          'search/:search/id/:id/chat',
          'page/:page/id/:id/chat',
          'search/:search/page/:page/id/:id/chat',
        ],
        title: 'ROCKETCHATWEBCLIENT.ADMIN_SETTINGS_TAB_LABEL',
        component () {
          return import('./components/RocketChatAdminSettingsPerTenant')
        },
      }
    ]
  }
}
