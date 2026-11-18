/**
 * happy-dom has no ElementInternals. Web Awesome form controls (wa-button)
 * that are put into the document then fail on their first update. Tests
 * that add such an element to the document themselves (for example the
 * button of a toast) install this small stand-in first.
 */
export function installElementInternals(): void {
  const prototype = HTMLElement.prototype as { attachInternals: () => ElementInternals }

  if (typeof (prototype.attachInternals as unknown) === 'function') {
    try {
      document.createElement('div').attachInternals()

      return
    } catch {
      // Present but not usable: replaced below.
    }
  }

  prototype.attachInternals = function () {
    return {
      form: null,
      states: new Set<string>(),
      validity: { valid: true },
      willValidate: false,
      validationMessage: '',
      setFormValue() {},
      setValidity() {},
      checkValidity: () => true,
      reportValidity: () => true
    } as unknown as ElementInternals
  }
}
