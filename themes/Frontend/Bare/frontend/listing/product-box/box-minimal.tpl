{extends file="frontend/listing/product-box/box-basic.tpl"}

{namespace name="frontend/listing/box_article"}

{block name='frontend_listing_box_article_description'}{/block}

{block name='frontend_listing_box_article_actions'}{/block}

{block name='frontend_listing_box_article_price'}
    <div class="product--price-outer">
        <div class="product--price">

            {* Discount price *}
            {block name='frontend_listing_box_article_price_discount'}
                {if $sArticle.has_pseudoprice}
                    <span class="price--pseudo">

                        {block name='frontend_listing_box_article_price_discount_before'}
                            {s name="priceDiscountLabel" namespace="frontend/detail/data"}{/s}
                        {/block}

                        <span class="price--discount is--nowrap">
                            {$sArticle.pseudoprice|currency}
                            {s name="Star"}{/s}
                        </span>

                        {block name='frontend_listing_box_article_price_discount_after'}
                            {s name="priceDiscountInfo" namespace="frontend/detail/data"}{/s}
                        {/block}
                    </span>
                {/if}
            {/block}

            {* Default price *}
            {block name='frontend_listing_box_article_price_default'}
                <span class="price--default is--nowrap{if $sArticle.has_pseudoprice} is--discount{/if}">
                    {if $sArticle.priceStartingFrom}{s name='ListingBoxArticleStartsAt'}{/s} {/if}
                    {$sArticle.price|currency}
                    {s name="Star"}{/s}
                </span>
            {/block}

            {* Regulation price *}
            {block name='frontend_listing_box_article_price_regulation'}
                {if $sArticle.regulationPrice}
                    <div class="price--regulation">

                        {block name='frontend_listing_box_article_price_regulation_before'}
                            {s name="priceRegulationLabel" namespace="frontend/detail/data"}{/s}
                        {/block}

                        {$sArticle.regulationPrice|currency}{s name="Star" namespace="frontend/listing/box_article"}{/s}

                        {block name='frontend_listing_box_article_price_regulation_after'}
                            {s name="priceRegulationInfo" namespace="frontend/detail/data"}{/s}
                        {/block}
                    </div>
                {/if}
            {/block}
        </div>
    </div>
{/block}
