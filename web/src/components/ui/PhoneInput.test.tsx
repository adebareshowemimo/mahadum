import { useState } from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { PhoneInput } from './PhoneInput'

function Form() {
  const [code, setCode] = useState('+234')
  const [phone, setPhone] = useState('')
  return <>
    <PhoneInput label="Phone number" value={phone} onChange={setPhone} dialCodeValue={code} onDialCodeChange={setCode} />
    <output aria-label="Submitted calling code">{code}</output>
  </>
}

describe('PhoneInput country selection', () => {
  it('includes worldwide countries and preserves countries sharing a calling code', async () => {
    const user = userEvent.setup()
    render(<Form />)
    const select = screen.getByRole('combobox', { name: 'Country calling code' })
    expect(select).toHaveValue('NG')
    expect(screen.getAllByRole('option').length).toBeGreaterThan(240)
    for (const country of ['Canada', 'United States', 'India', 'Brazil', 'Japan', 'New Zealand']) {
      expect(screen.getByRole('option', { name: new RegExp(`^${country} \\(`) })).toBeInTheDocument()
    }
    await user.selectOptions(select, 'CA')
    expect(select).toHaveValue('CA')
    expect(screen.getByLabelText('Submitted calling code')).toHaveTextContent('+1')
    await user.type(screen.getByLabelText('Phone number'), '4165550123')
    expect(select).toHaveValue('CA')
    await user.selectOptions(select, 'US')
    expect(select).toHaveValue('US')
    expect(screen.getByLabelText('Submitted calling code')).toHaveTextContent('+1')
    await user.selectOptions(select, 'IN')
    expect(screen.getByLabelText('Submitted calling code')).toHaveTextContent('+91')
    expect(screen.getByLabelText('Phone number')).toHaveValue('4165550123')
  })
})
