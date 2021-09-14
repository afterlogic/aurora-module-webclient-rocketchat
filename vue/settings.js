import _ from 'lodash'

import typesUtils from 'src/utils/types'

class RocketChatWebclientSettings {
  constructor (appData) {
    const rocketChatWebclientData = typesUtils.pObject(appData.RocketChatWebclient)
    if (!_.isEmpty(rocketChatWebclientData)) {
      this.chatUrl = rocketChatWebclientData.ChatUrl
      this.adminUsername = rocketChatWebclientData.AdminUsername
    }
  }

  saveRocketChatWebclientSettings ({ chatUrl, adminUsername, adminPassword }) {
    this.chatUrl = chatUrl
    this.adminUsername= adminUsername
  }
}

let settings = null

export default {
  init (appData) {
    settings = new RocketChatWebclientSettings(appData)
  },
  saveRocketChatWebclientSettings (data) {
    settings.saveRocketChatWebclientSettings(data)
  },
  getRocketChatWebclientSettings () {
    return {
      chatUrl: settings?.chatUrl,
      adminUsername: settings?.adminUsername,
    }
  },

}
