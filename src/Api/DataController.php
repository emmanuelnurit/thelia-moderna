<?php

declare(strict_types=1);

namespace Moderna\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Model\CountryQuery;
use Thelia\Model\CountryI18nQuery;
use Thelia\Model\CustomerTitleQuery;

#[Route('/moderna-api/data', name: 'moderna_api_data_')]
class DataController extends AbstractController
{
    #[Route('/countries', name: 'countries', methods: ['GET'])]
    public function getCountries(Request $request): JsonResponse
    {
        try {
            $locale = $request->getLocale() ?: 'en_US';
            $visibleOnly = $request->query->getBoolean('visible', true);

            $query = CountryQuery::create()
                ->orderByIsoalpha2();

            if ($visibleOnly) {
                $query->filterByVisible(true);
            }

            $countries = $query->find();

            $result = [];
            foreach ($countries as $country) {
                // Get i18n title
                $i18n = CountryI18nQuery::create()
                    ->filterById($country->getId())
                    ->filterByLocale($locale)
                    ->findOne();

                // Fallback to en_US if locale not found
                if (!$i18n) {
                    $i18n = CountryI18nQuery::create()
                        ->filterById($country->getId())
                        ->filterByLocale('en_US')
                        ->findOne();
                }

                $result[] = [
                    'id' => $country->getId(),
                    'isocode' => $country->getIsocode(),
                    'isoalpha2' => $country->getIsoalpha2(),
                    'isoalpha3' => $country->getIsoalpha3(),
                    'visible' => $country->getVisible(),
                    'byDefault' => $country->getByDefault(),
                    'title' => $i18n ? $i18n->getTitle() : $country->getIsoalpha2(),
                ];
            }

            return new JsonResponse($result);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to load countries',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/titles', name: 'titles', methods: ['GET'])]
    public function getTitles(Request $request): JsonResponse
    {
        try {
            $locale = $request->getLocale() ?: 'en_US';

            $titles = CustomerTitleQuery::create()
                ->orderByPosition()
                ->find();

            $result = [];
            foreach ($titles as $title) {
                // Get translation using Thelia's virtual column method
                $title->setLocale($locale);
                $short = $title->getShort();
                $long = $title->getLong();

                // Fallback to en_US
                if (empty($short)) {
                    $title->setLocale('en_US');
                    $short = $title->getShort();
                    $long = $title->getLong();
                }

                $result[] = [
                    'id' => $title->getId(),
                    'short' => $short ?: '',
                    'long' => $long ?: '',
                    'byDefault' => (bool) $title->getByDefault(),
                ];
            }

            return new JsonResponse($result);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to load titles',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
