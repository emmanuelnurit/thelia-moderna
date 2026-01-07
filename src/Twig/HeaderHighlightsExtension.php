<?php

namespace ModernaBundle\Twig;

use Propel\Runtime\Propel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides header highlights data directly from database
 */
class HeaderHighlightsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('headerHighlights', [$this, 'getHeaderHighlights']),
        ];
    }

    public function getHeaderHighlights(string $displayType = 'desktop', string $locale = 'fr_FR'): array
    {
        $connection = Propel::getConnection();

        $sql = "
            SELECT
                h.id,
                h.display_type,
                h.image_block,
                h.category_id,
                hi18n.title,
                hi18n.call_to_action as cta,
                hi18n.url,
                hi18n.catchphrase,
                img.file as image_file
            FROM header_highlights h
            LEFT JOIN header_highlights_i18n hi18n ON h.id = hi18n.id AND hi18n.locale = :locale
            LEFT JOIN header_highlights_image img ON h.id = img.header_highlights_id
            WHERE h.display_type = :display_type
            ORDER BY h.image_block ASC
        ";

        $stmt = $connection->prepare($sql);
        $stmt->execute([
            ':display_type' => $displayType,
            ':locale' => $locale
        ]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $imageUrl = '';
            if ($row['image_file']) {
                $imageUrl = '/local/media/images/headerHighlights/' . $row['image_file'];
            }

            $results[] = [
                'ID' => $row['id'],
                'TITLE' => $row['title'] ?? '',
                'CTA' => $row['cta'] ?? '',
                'URL' => $row['url'] ?? '',
                'CATCHPHRASE' => $row['catchphrase'] ?? '',
                'IMAGE_URL' => $imageUrl,
                'IMAGE_BLOCK' => $row['image_block'],
                'CATEGORY_ID' => $row['category_id']
            ];
        }

        return $results;
    }
}
