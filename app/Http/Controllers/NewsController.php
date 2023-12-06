<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Article;
use App\Models\Source;
use App\Models\User;
use App\Models\UserArticle;
use App\Models\Category;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $query = isset($request->q) ? $request->q : '';
        $category = isset($request->category) ? $request->category : '';
        $source = isset($request->source) ? $request->source : '';
        $author = isset($request->author) ? $request->author : '';
        $from_date = isset($request->from_date) ? $request->from_date : '';
        $to_date = isset($request->to_date) ? $request->to_date : '';
        $filterIn = isset($request->filterIn) ? $request->filterIn : '';
        

        $article = Article::select(
                                    "articles.title", 
                                    "articles.headline", 
                                    "articles.description",
                                    "sources.name as source",
                                    "categories.name as category",
                                    "users.name as author",
                                    "articles.image",
                                    "articles.url",
                                    "articles.published_at"
                                )
                            ->leftJoin('sources', "sources.id", "articles.source_id")
                            ->leftJoin('categories', "categories.id", "articles.category_id")
                            ->leftJoin('user_articles', "user_articles.article_id", "articles.id")
                            ->leftJoin('users', "users.id", "user_articles.user_id")
                            ->when(!empty($query), function($model) use ($query) {
                                return $model->where('articles.title', 'like', "%" . $query . "%")
                                    ->orWhere('articles.headline', 'like', "%" . $query . "%")
                                    ->orWhere('articles.description', 'like', "%" . $query . "%");
                            })
                            ->when(!empty($from_date), function($model) use ($from_date) {
                                return $model->where("published_at", ">=", $from_date);
                            })
                            ->when(!empty($to_date), function($model) use ($to_date) {
                                return $model->where("published_at", "<=", $to_date);
                            })
                            ->when(!empty($filterIn) && !empty($query), function($model) use ($filterIn, $query) {
                                $filterIn = str_replace(" ", "", $filterIn);
                                if(strstr($filterIn, ",")) {
                                    $tbls = explode(",", $filterIn);
                                } else {
                                    $tbls[] = $filterIn;
                                }
                                if(in_array("category", $tbls)) {
                                    $model = $model->where('categories.name', 'like', "%" . $query . "%");
                                }
                                if(in_array("source", $tbls)) {
                                    $model = $model->where('sources.name', 'like', "%" . $query . "%");
                                }
                                if(in_array("author", $tbls)) {
                                    $model = $model->where('users.name', 'like', "%" . $query . "%");
                                }
                                return $model;
                            });

        $result = $article->limit(50)->get();

        return $result;
    }


    public function fetchApi($type = null, $page = 1)
    {
        ini_set("memory_limit","2048M");
        ini_set("max_execution_time","180");
        $totalPages = 0;
        $limit = 10;        // Default NYTimes APi limit
        
        $startDate = date("Y-m-d",strtotime("-1 days"));
        $shortStartDate = date("Ymd",strtotime("-1 days"));

        if($type === "nytimes") {
            // Get offset
            $offset = Article::whereDate('published_at', $startDate)->where('api', 'nytimes')->count();
            if($page == 1 && $offset > 0) {
                $page = (int)round($offset / $limit);
            }
            $curl = curl_init();
            $apikey = config('constants.NYTIMES_API_KEY');
            // dump($apikey);
            $url = 'https://api.nytimes.com/svc/search/v2/articlesearch.json?&api-key=' . $apikey . '&begin_date=' . $shortStartDate . '&page='.$page.'&sort=oldest';
            // dump($url);
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
            ));

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpcode === 200) {
                $response = json_decode($response);
                if ($response->status == "OK") {
                    $rawData = $response->response->docs;
                    // dd($rawData);
                    $totalPages = round($response->response->meta->hits / $limit);
                    $result = $this->parseData($rawData, "nytimes");
                    if($page < $totalPages) {
                        $page++;
                        sleep(5);
                        $this->fetchApi("nytimes", $page);
                    }
                    return $result;
                } 
            } else {
                // dump("API: No response");
                if($page < $totalPages) {
                    $page++;
                    sleep(5);
                    $this->fetchApi("nytimes", $page);
                }
            }
            // dump($page);
            // return $result;
        }
        elseif($type == "theguardian") {
            $apikey = config('constants.GUARDIAN_API_KEY');
            // dump($apikey);
            $url = 'https://content.guardianapis.com/search?show-fields=byline,headline,shortUrl,trailText,body,thumbnail,publication&q=body,thumbnail&format=json&from-date='.$startDate.'&order-by=oldest&pageSize=200&api-key='.$apikey;
            dump($url);
            $response = Http::get($url);
            // dump($response);
            if($response->successful()) {
                $news = $response->json();
                // dump($news);
                $newsdata = $news['response']['results'];

                $result = $this->parseData($newsdata, "theguardian");
                return $result;
            } else {
                dd("API: no response");
            }
            // return $result; 
        }
        elseif($type == "newsorg") {
            $apikey = config('constants.NEWS_API_KEY');
            $url = 'https://newsapi.org/v2/everything?q=us&from='.$startDate.'&sortBy=popularity&language=en&pageSize=100&apiKey='.$apikey;
            dump($url);
            $response = Http::get($url);
            // dd($response);
            if($response->successful()) {
                // $news = $response->json();
                $news = $response;
                $newsdata = $news['articles'];
                $result = $this->parseData($newsdata, "newsorg");
                return $result;
            } else {
                dd("ERROR!");
                

            }
        }
    }

    public function parseData($rawData = null, $type = null) {
        $result = [];
        if($type == "newsorg") {
            if(count($rawData) > 0) {
                // dump($rawData);
                foreach($rawData as $dt) {
                    $title = $dt['title'];
                    $uid = $dt['url'];

                    $articleExists = Article::where('uid', $uid)->first();
                    if(!$articleExists) {
                        $article = [];
                        $article['uid']          = $uid;
                        $article['api']          = $type;
                        $article['title']        = $title;
                        $article['headline']     = null;
                        $article['description']  = $dt['content'];
                        $article['url']          = $dt['url'];
                        $article['image']        = $dt['urlToImage'];
                        $article['published_at'] = date("Y-m-d H:i:s", strtotime($dt['publishedAt']));

                        // Add Source
                        if(isset($dt['source']['name'])) {
                            $source = Source::where('name', $dt['source']['name'])->first();
                            if(!$source) {
                                $source = Source::create(['name' => $dt['source']['name']]);
                            }
                            $article['source_id'] = $source->id;
                        }

                        
                        // Add article
                        $article = Article::create($article);

                        // Add Author: Person | Organization
                        if(!empty($dt['author'])) {
                            if(strstr($dt['author'], " (")) {
                                $persons = explode(" (", $dt['author']);
                                $email = $persons[0];
                                $name = $this->cleanString($persons[1]);
                            } else {
                                $name = $dt['author'];
                                $email = "";
                            }
                            
                            $userExists = User::where(['name' => $name, 'email' => $email])->first();
                            if(!$userExists) {
                                $user = User::create([
                                            'name' => $name,
                                            'email' => $email
                                        ]);
                            }

                            
                        }

                        if(isset($user)) {
                            // UserArticle 
                            UserArticle::create(['article_id' => $article->id, 'user_id' => $user->id]);
                            $result[] = $article->id;
                        }
                    }
                }
            }
        } elseif($type == "nytimes") {
            if(count($rawData) > 0) {
                foreach($rawData as $dt) {
                    $title = $dt->headline->main;
                    $uid = $dt->_id;

                    $articleExists = Article::where('uid', $uid)->first();
                    if(!$articleExists) {
                        $article = [];
                        $article['uid']          = $uid;
                        $article['api']          = $type;
                        $article['title']        = $title;
                        $article['headline']     = $dt->abstract;
                        $article['description']  = $dt->lead_paragraph;
                        $article['url']          = $dt->web_url;
                        $article['image']        = count($dt->multimedia) > 0 ? $dt->multimedia[0]->url : '';
                        $article['published_at'] = date("Y-m-d H:i:s", strtotime($dt->pub_date));

                        // Add Source
                        if(isset($dt->source)) {
                            $source = Source::where('name', $dt->source)->first();
                            if(!$source) {
                                $source = Source::create(['name' => $dt->source]);
                            }
                            $article['source_id'] = $source->id;
                        }

                        // Add Category
                        if(isset($dt->section_name)) {
                            $section_name = $dt->section_name;
                            $slug = $this->createSlug($section_name);

                            $category = Category::where('name', $dt->section_name)->first();
                            if(!$category) {
                                $category = Category::create(['name' => $dt->section_name, 'slug' => $slug]);
                            }
                            
                            // Sub cat
                            if(isset($dt->subsection_name)) {
                                $subsection_name = $dt->subsection_name;
                                $slug = $this->createSlug($subsection_name);

                                $subcategory = Category::where(['parent_id' => $category->id,
                                                            'name' => $dt->subsection_name])->first();
                                if(!$subcategory) {
                                    $category = Category::create(['parent_id' => $category->id,
                                                                    'name' => $dt->subsection_name,
                                                                    'slug' => $slug
                                                                ]);
                                }
                            }
                            $article['category_id'] = $category->id;
                        }

                        // Add article
                        $article = Article::create($article);

                        // Add Author: Person | Organization
                        if(!empty($dt->byline->person) && count($dt->byline->person) > 0) {
                            foreach($dt->byline->person as $person) {
                                $name = $person->firstname;
                                if(!empty($person->middlename)) {
                                    $name .= " " .$person->middlename;
                                }
                                if(!empty($person->lastname)) {
                                    $name .= " " .$person->lastname;
                                }
                                $userExists = User::where('name', $name)->first();
                                if(!$userExists) {
                                    $user = User::create([
                                                'name' => $name
                                            ]);
                                }

                            }
                        } else {
                            // Organization
                            $name = $dt->byline->organization;
                            $userExists = User::where('name', $name)->first();
                            if(!$userExists && !empty(trim($name))) {
                                $user = User::create([
                                                'name' => $name,
                                                'type' => 1                     // 1: Organization
                                            ]);
                            }

                        }

                        if(isset($user)) {
                            // UserArticle 
                            UserArticle::create(['article_id' => $article->id, 'user_id' => $user->id]);
                            $result[] = $article->id;
                        }
                    }
                }
            }
            
        } elseif($type == "theguardian") {
            if(count($rawData) > 0) {
                // dump($rawData);
                foreach($rawData as $dt) {
                    $title = $dt['webTitle'];
                    $uid = $dt['id'];

                    $articleExists = Article::where('uid', $uid)->first();
                    if(!$articleExists) {
                        $article = [];
                        $article['uid']          = $uid;
                        $article['api']          = $type;
                        $article['title']        = $title;
                        $article['headline']     = $dt['fields']['headline'];
                        $article['description']  = $dt['fields']['body'];
                        $article['url']          = $dt['fields']['shortUrl'];
                        $article['image']        = $dt['fields']['thumbnail'];
                        $article['published_at'] = date("Y-m-d H:i:s", strtotime($dt['webPublicationDate']));

                        // Add Source
                        if(isset($dt['fields']['publication'])) {
                            $source = Source::where('name', $dt['fields']['publication'])->first();
                            if(!$source) {
                                $source = Source::create(['name' => $dt['fields']['publication']]);
                            }
                            $article['source_id'] = $source->id;
                        }

                        // Add Category
                        if(isset($dt['pillarName'])) {
                            $section_name = $dt['pillarName'];
                            $slug = $this->createSlug($section_name);

                            $category = Category::where('name', $section_name)->first();
                            if(!$category) {
                                $category = Category::create(['name' => $section_name, 'slug' => $slug]);
                            }
                            
                            // Sub cat
                            if(isset($dt['sectionName'])) {
                                $subsection_name = $dt['sectionName'];
                                $slug = $dt['sectionId'];

                                $subcategory = Category::where(['parent_id' => $category->id,
                                                            'name' => $subsection_name])->first();
                                if(!$subcategory) {
                                    $category = Category::create(['parent_id' => $category->id,
                                                                    'name' => $subsection_name,
                                                                    'slug' => $slug
                                                                ]);
                                }
                            }
                            $article['category_id'] = $category->id;
                        }

                        // Add article
                        $article = Article::create($article);

                        // Add Author: Person | Organization
                        if(!empty($dt['fields']['byline'])) {
                            if(strstr($dt['fields']['byline'], " and ")) {
                                $persons = explode(" and ", $dt['fields']['byline']);
                            } else {
                                $persons[] = $dt['fields']['byline'];
                            }
                            foreach($persons as $name) {
                                $userExists = User::where('name', $name)->first();
                                if(!$userExists) {
                                    $user = User::create([
                                                'name' => $name
                                            ]);
                                }

                            }
                        }

                        if(isset($user)) {
                            // UserArticle 
                            UserArticle::create(['article_id' => $article->id, 'user_id' => $user->id]);
                            $result[] = $article->id;
                        }
                    }
                }
            }
        }


        return $result;
    }

    private function createSlug($string) {
        $lowerCase = strtolower($string);

        $slug = str_replace(" ", "-", $lowerCase);

        // Remove special characters using a regular expression
        return $this->cleanString($slug);
    }    

    private function cleanString($string) {
        // Remove special characters using a regular expression
        return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
    }
}
