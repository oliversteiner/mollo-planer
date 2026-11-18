import { describe, expect, it } from 'vitest'
import { deviceName } from '../../app/utils/device-name'

describe('name suggestion for a passkey', () => {
  it('names system and browser', () => {
    expect(deviceName('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15')).toBe('Mac · Safari')
    expect(deviceName('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36')).toBe('Windows · Chrome')
    expect(deviceName('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0')).toBe('Windows · Edge')
    expect(deviceName('Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1')).toBe('iPhone · Safari')
    expect(deviceName('Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0')).toBe('Linux · Firefox')
  })

  it('says nothing when nothing is recognised', () => {
    expect(deviceName('')).toBeNull()
    expect(deviceName('curl/8.0')).toBeNull()
  })
})
