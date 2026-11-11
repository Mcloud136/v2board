(function () {
  var API_HOSTS = ['origin.wxmuma.cn', 'api.wxmuma.cn']
  var AUTH_KEYS = ['authorization', 'token']

  function isAuthKey(key) {
    return AUTH_KEYS.indexOf(key) !== -1
  }

  function hasAuth(storage) {
    return AUTH_KEYS.some(function (key) {
      try { return !!storage.getItem(key) } catch (e) { return false }
    })
  }

  function isApiUrl(url) {
    try {
      var u = new URL(url, location.href)
      return API_HOSTS.indexOf(u.hostname) !== -1 || u.pathname.indexOf('/api/') === 0
    } catch (e) {
      return false
    }
  }

  function isSameHost(url) {
    try {
      return new URL(url, location.href).hostname === location.hostname
    } catch (e) {
      return false
    }
  }

  function getCsrfToken() {
    try {
      var m = document.cookie.match(/(?:^|;\s*)__Host-admin_csrf=([^;]+)/)
      return m ? m[1] : ''
    } catch (e) {
      return ''
    }
  }

  function isAdminPage() {
    try {
      var securePath = (window.settings && window.settings.secure_path) || 'cx136572'
      var path = location.pathname.replace(/\/+$/, '')
      return path === '/' + securePath.replace(/^\/+|\/+$/g, '')
    } catch (e) {
      return false
    }
  }

  function ensureAdminSessionMarker() {
    if (!isAdminPage()) return
    try {
      if (localStorage.getItem('authorization')) return
      if (window.settings && window.settings.authenticated === true) {
        localStorage.setItem('authorization', 'web-session')
        return
      }
      var xhr = new XMLHttpRequest()
      xhr.open('GET', '/api/v1/user/checkLogin', false)
      xhr.withCredentials = true
      xhr.setRequestHeader('Accept', 'application/json')
      xhr.send(null)
      if (xhr.status !== 200) return
      var data = JSON.parse(xhr.responseText)
      if (data && data.data && data.data.is_login) {
        localStorage.setItem('authorization', 'web-session')
      }
    } catch (e) {}
  }

  ensureAdminSessionMarker()

  if (window.fetch) {
    var origFetch = window.fetch
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || ''
      if (isApiUrl(url)) {
        init = init || {}
        init.credentials = 'include'
      }
      if (isSameHost(url)) {
        var token = getCsrfToken()
        if (token) {
          init = init || {}
          try {
            var headers = new Headers(init.headers || {})
            if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token)
            init.headers = headers
          } catch (e) {}
        }
      }
      return origFetch.call(this, input, init)
    }
  }

  var origOpen = XMLHttpRequest.prototype.open
  XMLHttpRequest.prototype.open = function (method, url) {
    this.__v2Api = isApiUrl(url)
    this.__v2SameHost = isSameHost(url)
    return origOpen.apply(this, arguments)
  }
  var origSend = XMLHttpRequest.prototype.send
  XMLHttpRequest.prototype.send = function () {
    if (this.__v2Api) this.withCredentials = true
    if (this.__v2SameHost) {
      var token = getCsrfToken()
      if (token) {
        try {
          this.setRequestHeader('X-CSRF-Token', token)
        } catch (e) {}
      }
    }
    return origSend.apply(this, arguments)
  }

  function logout() {
    try {
      var base = location.hostname === 'api.wxmuma.cn' ? '' : 'https://origin.wxmuma.cn'
      fetch(base + '/api/v1/user/logout', {
        method: 'POST',
        credentials: 'include',
        keepalive: true,
      })
    } catch (e) {}
  }

  var origRemove = Storage.prototype.removeItem
  Storage.prototype.removeItem = function (key) {
    var hadValue = false
    try {
      hadValue = !!this.getItem(key)
    } catch (e) {}
    if (isAuthKey(key) && hadValue) logout()
    return origRemove.apply(this, arguments)
  }

  var origSet = Storage.prototype.setItem
  Storage.prototype.setItem = function (key, value) {
    var hadValue = false
    try {
      hadValue = !!this.getItem(key)
    } catch (e) {}
    if (isAuthKey(key) && hadValue && (value == null || value === '' || value === 'null')) {
      logout()
    }
    return origSet.apply(this, arguments)
  }

  var origClear = Storage.prototype.clear
  Storage.prototype.clear = function () {
    var hadAuth = hasAuth(this)
    if (hadAuth) logout()
    return origClear.apply(this, arguments)
  }

  function enableAsyncFonts() {
    try {
      document.querySelectorAll('link[data-async-font]').forEach(function (link) {
        link.media = 'all'
      })
    } catch (e) {}
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enableAsyncFonts)
  } else {
    enableAsyncFonts()
  }
})()
