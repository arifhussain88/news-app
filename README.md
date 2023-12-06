Pre-requisites:
---------------------

PHP: 8 >
Laravel: 9
Mysql

---------------------
About Project:
---------------------
News App fetches data from NewsApi.org, TheGuardian, NYTimes

Data is updated regularly via Laravel Task schedules; commands are set to run regularly in App\Kernel

API Data can be fetched manually as well by hitting URLs:

	/fetchdata/nytimes
	/fetchdata/theguardian
	/fetchdata/newsorg



Api end-points for frontend are set at:
/newsapi

Parameters can be passed for querying data as required: 
	
	q: keyword to search in article title, headline and description
	from_date and to_date: to select any article published within date as grouped together or individually, e.g: from_date=2023-12-01&to_date=2023-12-05
	filterIn: to search data in category, source or author, multiple parameters can be passed comma seperated, e.g: filterIn=category,source


For fecthing data from API default limit is set to 50 records.
	



