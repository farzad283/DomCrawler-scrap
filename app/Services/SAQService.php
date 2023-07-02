<?php

namespace App\Services;

use App\Models\Bouteille;
use App\Models\Type;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;
use stdClass;

class SAQService 
{
    const DUPLICATION = 'duplication';
    const ERREURDB = 'erreurdb';
    const INSERE = 'Nouvelle bouteille insérée';

    public function getProduits($nombre = 48, $page)
    {
        $results = [];
        $url = "https://www.saq.com/fr/produits/vin?p=" . $page . "&product_list_limit=" . $nombre . "&product_list_order=name_asc";

        $client = new Client();
        $res = $client->request('GET', $url);
        $html = (string) $res->getBody();
        $crawler = new Crawler($html);

        $crawler->filter('.product-item')->each(function (Crawler $node) use (&$results) {
            $info = $this->recupereInfo($node);
            $result = $this->ajouteProduit($info);
            $resultData = [
                'nom' => $info->nom,
                'retour' => [
                    'succes' => $result->succes,
                    'raison' => $result->raison,
                ],
            ];

            $results[] = $resultData;
        });

        return $results;
    }

    private function recupereInfo(Crawler $node)
    {
        $info = new stdClass();
        $info->img = $node->filter('img')->first()->attr('src');
        $a_titre = $node->filter('a')->first();
        $info->url = $a_titre->attr('href');
        $nom = $node->filter('a')->eq(1)->text();
        $info->nom = $this->nettoyerEspace(trim($nom));
    
        $node->filter('strong.product.product-item-identity-format')->each(function ($node) use (&$info) {
            $info->desc = new stdClass();
            $info->desc->texte = $node->text();
            $info->desc->texte = $this->nettoyerEspace($info->desc->texte);
            $aDesc = explode("|", $info->desc->texte); // Type, Format, Pays
            if (count($aDesc) == 3) {
                $info->desc->type = trim($aDesc[0]);
                $info->desc->format = trim($aDesc[1]);
                $info->desc->pays = trim($aDesc[2]);
            }
            $info->desc->texte = trim($info->desc->texte);
        });
    
        $node->filter('div.saq-code')->each(function ($node) use (&$info) {
            if(preg_match("/\d+/", $node->text(), $aRes)) {
                $info->desc->code_SAQ = trim($aRes[0]);
            }
        });
    
        $node->filter('span.price')->each(function ($node) use (&$info) {
            $prix= trim($node->text());
            $prix_nettoyer = str_replace("$","",$prix);
            $prix_point= str_replace(',',".",$prix_nettoyer);
            $info->prix = floatval($prix_point);
        });
    
        return $info;
    }
    

    private function ajouteProduit($bte)
    {
        $retour = new stdClass();
        $retour->succes = false;
        $retour->raison = '';

        $type = Type::where('type', $bte->desc->type)->first();

        if ($type) {
            $rows = Bouteille::where('code_saq', $bte->desc->code_SAQ)->count();

            if ($rows < 1) {
                $nouvelleBouteille = new Bouteille();
                $nouvelleBouteille->nom = $bte->nom;
                $nouvelleBouteille->type = $type->id;
                $nouvelleBouteille->image = $bte->img;
                $nouvelleBouteille->code_saq = $bte->desc->code_SAQ;
                $nouvelleBouteille->pays = $bte->desc->pays;
                $nouvelleBouteille->description = $bte->desc->texte;
                $nouvelleBouteille->prix_saq = $bte->prix;
                $nouvelleBouteille->url_saq = $bte->url;
                $nouvelleBouteille->url_img = $bte->img;
                $nouvelleBouteille->format = $bte->desc->format;

                $retour->succes = $nouvelleBouteille->save();
                $retour->raison = self::INSERE;
            } else {
                $retour->succes = false;
                $retour->raison = self::DUPLICATION;
            }
        } else {
            $retour->succes = false;
            $retour->raison = self::ERREURDB;
        }
        return $retour;
    }

    private function nettoyerEspace($chaine)
    {
        return preg_replace('/\s+/', ' ', $chaine);
    }

    public function fetchProduit()
    {
        $pages = 345;
        $perPage = 24;
        $currentPage = 1;
        $results = [];

        while ($currentPage <= $pages) {
            $results[] = $this->getProduits($perPage, $currentPage);
            $currentPage++;
            usleep(100000);
        }
        return $results;
    }
}
