<script setup lang="ts">
/** Frame of the pages without login (login, password, invitation): small header, one centred column. */
</script>

<template>
  <wa-page>
    <header
      slot="header"
      class="auth-header"
    >
      <AppLogo class="auth-logo" />
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
  color: #612692;
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
</style>
