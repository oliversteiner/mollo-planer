<script setup lang="ts">
/** Frame of the pages without login (login, password, invitation): small header, one centred column. */
const { t } = useI18n()
</script>

<template>
  <wa-page>
    <header
      slot="header"
      class="auth-header"
    >
      <NuxtLink
        to="/"
        class="auth-logo"
        :aria-label="t('app.home')"
      >
        <AppLogo />
      </NuxtLink>
      <div class="auth-tools">
        <LocaleSwitch />
        <ColorModeSwitch />
      </div>
    </header>

    <main class="auth-main">
      <div class="auth-column">
        <slot />
      </div>
    </main>

    <footer
      slot="footer"
      class="auth-footer"
    >
      <a href="/mcp/">MCP</a>
    </footer>

    <PasswordPromptModal />
  </wa-page>
</template>

<style scoped>
.auth-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wa-space-s);
  padding: var(--wa-space-m);
}

.auth-logo {
  display: inline-block;
  color: #612692;
  text-decoration: none;
}

.auth-logo:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

:root.wa-dark .auth-logo {
  color: var(--wa-color-brand-on-quiet);
}

.auth-tools {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
}

.auth-main {
  display: flex;
  justify-content: center;
  padding: var(--wa-space-xl) var(--wa-space-m);
}

.auth-column {
  inline-size: 100%;
  max-inline-size: 24rem;
}

.auth-footer {
  padding: var(--wa-space-m);
  text-align: center;
}

.auth-footer a {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  text-decoration: none;
}

.auth-footer a:hover {
  color: var(--wa-color-text-normal);
  text-decoration: underline;
}

.auth-footer a:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}
</style>
