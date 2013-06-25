<?php

/**
 * Handles movie-related actions
 *
 * @author Sam Stenvall <neggelandia@gmail.com>
 */
class MovieController extends VideoLibraryController
{

	/**
	 * Lists all movies in the library, optionally filtered
	 */
	public function actionIndex()
	{
		// Start building the request parameters
		$requestParameters = array(
			'properties'=>array('thumbnail'),
			'sort'=>array(
				'order'=>self::SORT_ORDER_ASCENDING, 'method'=>'label'));

		// Get filter properties
		$movieFilterForm = new MovieFilterForm();
		$nativeFilters = array();

		if (isset($_GET['MovieFilterForm']))
		{
			$movieFilterForm->attributes = $_GET['MovieFilterForm'];

			if ($movieFilterForm->validate())
			{
				$nativeFilters['title'] = $movieFilterForm->name;
				$nativeFilters['genre'] = $movieFilterForm->genre;
				$nativeFilters['year'] = $movieFilterForm->year;

				$nativeFilters = array_filter($nativeFilters);
			}
		}

		// Add filter request parameter. If no filter is defined the parameter 
		// must be omitted.
		foreach ($nativeFilters as $field=> $value)
		{
			$filter = new stdClass();
			$filter->field = $field;
			$filter->operator = 'is';
			$filter->value = $value;

			if (!isset($requestParameters['filter']))
				$requestParameters['filter'] = new stdClass();

			$requestParameters['filter']->and[] = $filter;
		}

		// Get the movies
		$response = $this->performRequest('VideoLibrary.GetMovies', $requestParameters);

		if (isset($response->result->movies))
			$movies = $response->result->movies;
		else
			$movies = array();

		// If there is only one item in the result we redirect directly to the 
		// details page
		if (count($movies) == 1)
			$this->redirect(array('details', 'id'=>$movies[0]->movieid));

		$this->registerScripts();
		
		$this->render('index', array(
			'dataProvider'=>new LibraryDataProvider($movies, 'movieid'),
			'movieFilterForm'=>$movieFilterForm));
	}
	
	/**
	 * Shows details and download links for the specified movie
	 * @param int $id the movie ID
	 */
	public function actionDetails($id)
	{
		$response = $this->performRequest('VideoLibrary.GetMovieDetails', array(
			'movieid'=>(int)$id,
			'properties'=>array(
				'title',
				'genre',
				'year',
				'rating',
//				'director',
//				'trailer',
				'tagline',
				'plot',
//				'plotoutline',
//				'originaltitle',
//				'lastplayed',
//				'playcount',
//				'writer',
//				'studio',
				'mpaa',
				'cast',
//				'country',
				'imdbnumber',
				'runtime',
//				'set',
//				'showlink',
				'streamdetails',
//				'top250',
				'votes',
//				'fanart',
				'thumbnail',
				'file',
//				'sorttitle',
//				'resume',
//				'setid',
//				'dateadded',
//				'tag',
//				'art',
			)
		));

		$this->registerScripts();

		$movieDetails = $response->result->moviedetails;

		// Create a data provider for the actors. We only show one row (first 
		// credited only), hence the 6
		$actorDataProvider = new CArrayDataProvider(
				$movieDetails->cast, array(
			'keyField'=>'name',
			'pagination'=>array('pageSize'=>6)
		));

		$movieLinks = $this->getMovieLinks($movieDetails);

		$this->render('details', array(
			'details'=>$movieDetails,
			'actorDataProvider'=>$actorDataProvider,
			'movieLinks'=>$movieLinks,
		));
	}
	
	/**
	 * Serves a playlist containing the specified movie's files to the browser
	 * @param int $movieId the movie ID
	 */
	public function actionGetMoviePlaylist($movieId)
	{
		$response = $this->performRequest('VideoLibrary.GetMovieDetails', array(
			'movieid'=>(int)$movieId,
			'properties'=>array(
				'file',
				'runtime',
				'title',
				'year')));

		$movieDetails = $response->result->moviedetails;
		$links = $this->getMovieLinks($movieDetails);
		$name = $movieDetails->title.' ('.$movieDetails->year.')';
		$playlist = new M3UPlaylist();
		$linkCount = count($links);

		foreach ($links as $k=> $link)
		{
			$label = $linkCount > 1 ? $name.' (#'.++$k.')' : $name;
			
			$playlist->addItem(array(
				'runtime'=>(int)$movieDetails->runtime,
				'label'=>$label,
				'url'=>$link));
		}

		header('Content-Type: audio/x-mpegurl');
		header('Content-Disposition: attachment; filename="'.$name.'.m3u"');

		echo $playlist;
	}

	/**
	 * Returns an array with the download links for a movie. It takes the a 
	 * result from GetMovieDetails as parameter.
	 * @param stdClass $movieDetails
	 * @return array the download links
	 */
	private function getMovieLinks($movieDetails)
	{
		$rawFiles = array();
		$files = array();

		// Check for multiple files
		// TODO: Maybe just check for stack://?
		if (strpos($movieDetails->file, ' , ') !== false)
			$rawFiles = preg_split('/ , /i', $movieDetails->file);
		else
			$rawFiles[] = $movieDetails->file;

		foreach ($rawFiles as $rawFile)
		{
			// Detect and remove stack://
			if (substr($rawFile, 0, 8) === 'stack://')
				$rawFile = substr($rawFile, 8);

			// Create the URL to the movie
			$response = $this->performRequest('Files.PrepareDownload', array(
				'path'=>$rawFile));

			$files[] = $this->getAbsoluteVfsUrl($response->result->details->path);
		}

		return $files;
	}
	
	/**
	 * Returns an array containing all movie names
	 * TODO: Make a generic VideoLibrary model with getMovies() method
	 * @return array the names
	 */
	public function getMovieNames()
	{
		// Get the list of movies along with their thumbnails
		$response = $this->performRequest('VideoLibrary.GetMovies');

		// Sort the results
		$movies = $response->result->movies;
		$this->sortResults($movies);

		$names = array();
		foreach ($movies as $movie)
			$names[] = $movie->label;

		return $names;
	}
	
}