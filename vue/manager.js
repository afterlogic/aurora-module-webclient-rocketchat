import settings from './settings'
import store from 'src/store'

import RocketChatAdminSettingsPerTenant from './components/RocketChatAdminSettingsPerTenant'

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
        tabTitle: 'ROCKETCHATWEBCLIENT.ADMIN_SETTINGS_TAB_LABEL',
        tabRouteChildren: [
          { path: 'chat', component: () => import('./components/RocketChatAdminSettings') },
        ],
      },
    ]
  },

  getAdminTenantTabs () {
    const isUserSuperAdmin = store.getters['user/isUserSuperAdmin']
    if (isUserSuperAdmin) {
      return [
        {
          tabName: 'chat',
          tabTitle: 'ROCKETCHATWEBCLIENT.ADMIN_SETTINGS_TAB_LABEL',
          tabRouteChildren: [
            { path: 'id/:id/chat', component: RocketChatAdminSettingsPerTenant },
            { path: 'search/:search/id/:id/chat', component: RocketChatAdminSettingsPerTenant },
            { path: 'page/:page/id/:id/chat', component: RocketChatAdminSettingsPerTenant },
            { path: 'search/:search/page/:page/id/:id/chat', component: RocketChatAdminSettingsPerTenant },
          ],
        }
      ]
    } else {
      return []
    }
  },
}
