/**
 * Voice input with the browser's speech recognition (Web Speech API).
 * Chrome and Edge send the audio to Google or Microsoft for recognition;
 * Firefox has none, so `supported` is false there and no button shows.
 */

interface SpeechAlternative { transcript: string }
interface SpeechResult { isFinal: boolean, 0: SpeechAlternative }
interface SpeechResultEvent { resultIndex: number, results: ArrayLike<SpeechResult> }
interface SpeechErrorEvent { error: string }
interface SpeechRecognitionLike {
  lang: string
  continuous: boolean
  interimResults: boolean
  onresult: ((event: SpeechResultEvent) => void) | null
  onerror: ((event: SpeechErrorEvent) => void) | null
  onend: (() => void) | null
  start(): void
  stop(): void
}
type SpeechRecognitionConstructor = new () => SpeechRecognitionLike

function recognitionClass(): SpeechRecognitionConstructor | null {
  if (typeof window === 'undefined') {
    return null
  }

  const speechWindow = window as unknown as {
    SpeechRecognition?: SpeechRecognitionConstructor
    webkitSpeechRecognition?: SpeechRecognitionConstructor
  }

  return speechWindow.SpeechRecognition ?? speechWindow.webkitSpeechRecognition ?? null
}

/**
 * @param language BCP 47 tag, for example "de-CH"
 * @param onText   called with each finished sentence
 */
export function useSpeechInput(language: () => string, onText: (text: string) => void) {
  const supported = ref(false)
  const listening = ref(false)
  /** What is being recognized right now, not final yet. */
  const interim = ref('')
  const error = ref<string | null>(null)
  let recognition: SpeechRecognitionLike | null = null

  onMounted(() => {
    supported.value = recognitionClass() !== null
  })

  function start() {
    const Recognition = recognitionClass()

    if (!Recognition || listening.value) {
      return
    }

    error.value = null
    recognition = new Recognition()
    recognition.lang = language()
    recognition.continuous = true
    recognition.interimResults = true
    recognition.onresult = (event) => {
      let pending = ''

      for (let index = event.resultIndex; index < event.results.length; index++) {
        const result = event.results[index]!

        if (result.isFinal) {
          onText(result[0].transcript.trim())
        } else {
          pending += result[0].transcript
        }
      }

      interim.value = pending
    }
    recognition.onerror = (event) => {
      // "no-speech" and "aborted" are normal ends, not errors to show.
      if (event.error !== 'no-speech' && event.error !== 'aborted') {
        error.value = event.error
      }
    }
    recognition.onend = () => {
      listening.value = false
      interim.value = ''
    }
    recognition.start()
    listening.value = true
  }

  function stop() {
    recognition?.stop()
  }

  function toggle() {
    if (listening.value) {
      stop()
    } else {
      start()
    }
  }

  onBeforeUnmount(() => recognition?.stop())

  return { supported, listening, interim, error, start, stop, toggle }
}
