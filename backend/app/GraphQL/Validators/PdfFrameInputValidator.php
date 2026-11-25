<?php

namespace App\GraphQL\Validators;

use App\Core\Pdf\PdfFonts;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

/**
 * Layout, font and window of the PDF frame (Auftrag 39 F): values in mm within sensible limits; the address field
 * lies on the page and above the start of the text on page 1.
 */
class PdfFrameInputValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $layout = (array) $this->arg('layout');

        return [
            'layout.topFirst' => ['required', 'numeric', 'min:10', 'max:200', 'gte:layout.addressTop'],
            'layout.topFollowing' => ['required', 'numeric', 'min:5', 'max:150'],
            'layout.bottom' => ['required', 'numeric', 'min:5', 'max:100'],
            'layout.left' => ['required', 'numeric', 'min:5', 'max:60'],
            'layout.right' => ['required', 'numeric', 'min:5', 'max:60'],
            'layout.addressLeft' => ['required', 'numeric', 'min:0', 'max:'.max(0, 210 - (float) ($layout['addressWidth'] ?? 0))],
            'layout.addressTop' => ['required', 'numeric', 'min:0', 'max:'.max(0, (float) ($layout['topFirst'] ?? 0) - (float) ($layout['addressHeight'] ?? 0))],
            'layout.addressWidth' => ['required', 'numeric', 'min:40', 'max:120'],
            'layout.addressHeight' => ['required', 'numeric', 'min:15', 'max:60'],
            'font' => ['required', Rule::in(array_keys(PdfFonts::ALL))],
            'addressWindow' => ['required', Rule::in(['left', 'right'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'layout.addressLeft.max' => __('pdf.address_outside'),
            'layout.addressTop.max' => __('pdf.address_below_text'),
        ];
    }
}
