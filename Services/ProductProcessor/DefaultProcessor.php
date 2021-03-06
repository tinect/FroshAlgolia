<?php

namespace FroshAlgolia\Services\ProductProcessor;

use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Models\Media\Media;
use FroshAlgolia\Structs\Article;

class DefaultProcessor implements ProcessorInterface
{
    /**
     * @param Product $product    Shopware Product
     * @param Article $article    Algolia Product
     * @param array   $shopConfig Shop Configuration
     */
    public function process(Product $product, Article $article, array $shopConfig)
    {
        // Get the media
        $media = $product->getMedia();
        $image = null;

        if (!empty($media)) {
            /** @var Media $mediaObject */
            $mediaObject = current($media);
            $image = $mediaObject->getThumbnail(0)->getSource();
        }

        // Get the votes
        $voteAvgPoints = 0;
        $votes = $product->getVoteAverage();
        if ($votes) {
            $voteAvgPoints = (int) $votes->getPointCount()[0]['points'];
        }

        // Buid the article struct
        $article->setObjectID($product->getNumber());
        $article->setArticleId($product->getId());
        $article->setName($product->getName());
        $article->setNumber($product->getNumber());
        $article->setManufacturerName($product->getManufacturer()->getName());
        $article->setPrice(round($product->getCheapestPrice()->getCalculatedPrice(), 2));
        $article->setDescription(strip_tags($product->getShortDescription()));
        $article->setEan($product->getEan());
        $article->setImage($image);
        $article->setCategories($this->getCategories($product));
        $article->setAttributes($this->getAttributes($product, $shopConfig));
        $article->setProperties($this->getProperties($product));
        $article->setSales($product->getSales());
        $article->setVotes($votes);
        $article->setVoteAvgPoints($voteAvgPoints);
    }

    /**
     * Get all product attributes.
     *
     * @param Product $product
     *
     * @return array
     */
    private function getAttributes(Product $product, $shopConfig)
    {
        $data = [];

        if (!isset($product->getAttributes()['core'])) {
            return [];
        }

        $attributes = $product->getAttributes()['core']->toArray();
        $blockedAttributes = array_column($shopConfig['blockedAttributes'], 'name');

        foreach ($attributes as $key => $value) {
            // Skip this attribute if it´s on the list of blocked attributes
            if (in_array($key, $blockedAttributes) || empty($value)) {
                continue;
            }

            // Map value to data array
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Prepare categories for data article.
     *
     * @param Product $product
     *
     * @return array
     */
    private function getCategories(Product $product)
    {
        $categoriesBad = $product->getCategories();
        $categories = [];

        foreach ($categoriesBad as $item) {
            $categories[$item->getId()] = $item;
        }

        $cats = Shopware()->Db()->fetchAll('SELECT s_categories.id, path, description FROM s_articles_categories INNER JOIN s_categories ON(s_categories.id = s_articles_categories.categoryID) WHERE articleID = ?', [
            $product->getId()
        ]);

        foreach ($cats as &$cat) {
            $cat['path'] = array_filter(explode('|', $cat['path']));
            foreach ($cat['path'] as &$item) {
                $item = $categories[$item]->getName();
            }
            $cat['path'] = array_reverse($cat['path']);
            $cat['path'][] = $cat['description'];
            unset($cat['path'][0]);

        }

        $data = [];

        foreach ($cats as $category) {
//            $row = [
//                'objectID' => $category['id'],
//                'name' => $category['description'],
//            ];

            foreach ($category['path'] as $i => $lol) {
                $row['lvl' . ($i -1)] = array_chunk($category['path'], $i)[0];
            }

            $data[] = $row;
        }

        return $data;
    }

    /**
     * Fetches all product properties as an array.
     *
     * @param Product $product
     *
     * @return array
     */
    private function getProperties(Product $product)
    {
        $properties = [];

        if ($set = $product->getPropertySet()) {
            $groups = $set->getGroups();

            foreach ($groups as $group) {
                $options = $group->getOptions();

                foreach ($options as $option) {
                    $properties[$group->getName()] = $option->getName();
                }
            }
        }

        return $properties;
    }
}
