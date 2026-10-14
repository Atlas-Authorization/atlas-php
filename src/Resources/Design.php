<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * Sign-in page design: the instance design, previews, uploaded images and the catalog.
 *
 * A design is an associative array of optional sections (keys are camelCase, as
 * sent; colours are `#rrggbb`). Unknown keys are refused with their path. The
 * full shape is typed below as `AtlasDesign`; `AtlasDesignPatch` is the same
 * with every value also accepting null (null removes a setting).
 *
 *     $asset = $atlas->design->uploadAsset(file_get_contents('hero.jpg'), 'background');
 *     $atlas->design->update(['preset' => 'glass', 'background' => ['image' => ['assetId' => $asset['asset_id']]]]);
 *
 * @phpstan-type DesignColor string
 * @phpstan-type DesignPalette array{primary?:DesignColor,primaryInk?:DesignColor,accent?:DesignColor,background?:DesignColor,surface?:DesignColor,text?:DesignColor,muted?:DesignColor,border?:DesignColor,danger?:DesignColor,success?:DesignColor,inputBackground?:DesignColor,inputText?:DesignColor}
 * @phpstan-type DesignFont 'system'|'system-serif'|'system-mono'|'inter'|'roboto'|'open-sans'|'lato'|'montserrat'|'poppins'|'nunito'|'work-sans'|'dm-sans'|'manrope'|'source-serif'|'merriweather'|'playfair-display'|'jetbrains-mono'|'ibm-plex-mono'
 * @phpstan-type DesignTypography array{font?:DesignFont,headingFont?:DesignFont,baseSize?:int,headingWeight?:int,bodyWeight?:int,letterCase?:'none'|'uppercase',scale?:'minor-second'|'major-second'|'minor-third'|'major-third'|'perfect-fourth'|'augmented-fourth'|'perfect-fifth'|'golden'|'minor-seventh'|'octave'}
 * @phpstan-type DesignShape array{radius?:int,controlRadius?:int,borderWidth?:int}
 * @phpstan-type DesignElevation array{cardShadow?:'none'|'sm'|'md'|'lg'|'xl'}
 * @phpstan-type DesignSpacing array{density?:'compact'|'comfortable'|'spacious'}
 * @phpstan-type DesignCard array{style?:'solid'|'glass'|'outline'|'none',blur?:int,width?:'narrow'|'normal'|'wide'}
 * @phpstan-type DesignLayout array{position?:'center'|'left'|'right'|'split',splitImageSide?:'left'|'right',logoPlacement?:'card'|'page'|'hidden',logoSize?:'sm'|'md'|'lg'}
 * @phpstan-type DesignButtons array{style?:'solid'|'outline'|'soft'|'gradient',socialLayout?:'list'|'grid'|'icons'}
 * @phpstan-type DesignInputs array{style?:'outline'|'filled'|'underline'}
 * @phpstan-type DesignGradient array{type:'linear'|'radial',angle?:int,stops:list<array{color:DesignColor,at:float|int}>}
 * @phpstan-type DesignImage array{assetId:string,fit?:'cover'|'contain',position?:'top-left'|'top'|'top-right'|'left'|'center'|'right'|'bottom-left'|'bottom'|'bottom-right',overlay?:array{color:DesignColor,opacity:float|int},blur?:int}
 * @phpstan-type DesignBackground array{color?:DesignColor,gradient?:DesignGradient,image?:DesignImage,pattern?:'none'|'dots'|'grid'|'noise'}
 * @phpstan-type DesignLogo array{assetId?:string,darkAssetId?:string}
 * @phpstan-type AtlasDesign array{version?:1,preset?:'minimal'|'atlas'|'glass'|'midnight'|'editorial'|'terminal'|'sunrise'|'forest'|'grape'|'slate'|'split-photo'|'bold',colorScheme?:'auto'|'light'|'dark',palette?:DesignPalette,paletteDark?:DesignPalette,typography?:DesignTypography,shape?:DesignShape,elevation?:DesignElevation,spacing?:DesignSpacing,card?:DesignCard,layout?:DesignLayout,buttons?:DesignButtons,inputs?:DesignInputs,background?:DesignBackground,backgroundDark?:DesignBackground,logo?:DesignLogo}
 * @phpstan-type AtlasDesignPatch array<string,mixed>
 * @phpstan-type DesignWarning array<string,mixed>
 * @phpstan-type DesignSettings array{object:'design'|'organization_design',organization_id?:string,design:AtlasDesign,resolved:array<string,mixed>,warnings:list<DesignWarning>,legacy:bool,legacy_design?:AtlasDesign|null}
 * @phpstan-type DesignPreview array{object:'design_preview',scope:string,css:string,data_attributes:array<string,string>,font_stylesheets:list<string>,resolved:array<string,mixed>,warnings:list<DesignWarning>,html:string}
 * @phpstan-type DesignAsset array{object:'design_asset',asset_id:string,kind:'background'|'logo',url:string,width:int,height:int,dominant_color:string|null,bytes:int,created_at:string}
 */
final class Design extends Resource
{
    /** The top-level design sections. */
    public const SECTIONS = [
        'version', 'preset', 'colorScheme', 'palette', 'paletteDark', 'typography', 'shape', 'elevation',
        'spacing', 'card', 'layout', 'buttons', 'inputs', 'background', 'backgroundDark', 'logo',
    ];

    /**
     * `GET /v1/instance/design` — the instance's sign-in page design: stored
     * overrides, the resolved design and contrast warnings.
     *
     * @return DesignSettings
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance/design');
    }

    /**
     * `PUT /v1/instance/design` — replace the instance design.
     *
     * @param AtlasDesign $design
     *
     * @return DesignSettings
     */
    public function replace(array $design): array
    {
        return $this->http->request(
            'PUT',
            '/v1/instance/design',
            body: ['design' => $design === [] ? new \stdClass() : $design],
        );
    }

    /**
     * `PATCH /v1/instance/design` — change part of the instance design: nested
     * settings merge, null removes a setting.
     *
     * @param AtlasDesignPatch $design
     *
     * @return DesignSettings
     */
    public function update(array $design): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/instance/design',
            body: ['design' => $design === [] ? new \stdClass() : $design],
        );
    }

    /**
     * `POST /v1/instance/design/preview` — compile an unsaved design (CSS
     * variables, data attributes, fonts, warnings, rendered page). Nothing is saved.
     *
     * @param AtlasDesign $design
     *
     * @return DesignPreview
     */
    public function preview(array $design): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/design/preview',
            body: ['design' => $design === [] ? new \stdClass() : $design],
        );
    }

    /**
     * `GET /v1/design/assets` — this instance's uploaded design images, newest first.
     *
     * @return array{object:string,data:list<DesignAsset>}
     */
    public function listAssets(): array
    {
        return $this->http->request('GET', '/v1/design/assets');
    }

    /**
     * `DELETE /v1/design/assets/:id` — delete a design image. Refused with 409
     * while a saved design still uses it (`meta.used_by` lists where).
     *
     * @return array{object:string,asset_id:string,deleted:bool}
     */
    public function deleteAsset(string $assetId): array
    {
        return $this->http->request('DELETE', '/v1/design/assets/' . rawurlencode($assetId));
    }

    /**
     * `GET /v1/design/catalog` — the design catalog: presets, curated fonts and
     * the CSS variable set.
     *
     * @return array<string,mixed>
     */
    public function catalog(): array
    {
        return $this->http->request('GET', '/v1/design/catalog');
    }

    /**
     * `POST /v1/design/assets` — upload a design image (a background or a logo):
     * raw PNG, JPEG, GIF or WebP bytes, up to 8 MB. The content type is detected
     * from the bytes unless given. Use the returned `asset_id` in a design
     * (`logo.assetId`, `background.image.assetId`).
     *
     *     $asset = $atlas->design->uploadAsset(file_get_contents('bg.jpg'), 'background');
     *
     * @param 'background'|'logo'|null $kind
     *
     * @return DesignAsset
     */
    public function uploadAsset(string $bytes, ?string $kind = null, ?string $contentType = null): array
    {
        return $this->uploadImage('/v1/design/assets', $bytes, $kind, $contentType);
    }
}
