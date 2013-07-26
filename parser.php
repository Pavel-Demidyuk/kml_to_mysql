<?php

$kmlToMysql = new Kml_To_Mysql('regions2010_wgs.KML');
$kmlToMysql->run();

class Kml_To_Mysql
{
	const DB_HOST = 'localhost';
	
	const DB_NAME = 'kml';
	
	const DB_TABLE_NAME = 'regions';
	
	const DB_USER = 'root';
	
	const DB_PASSWORD = 'root';
	
	/**
	 * @var \mysqli
	 */
	protected $db;
	
	/**
	 * @var string
	 */
	protected $kmlFileName;
	
	/**
	 * @var SimpleXMLElement
	 */
	protected $kmlContent;
	
	/**
	 * @param string $kmlFileName
	 */
	public function __construct($kmlFileName)
	{
		$this->kmlFileName = $kmlFileName;
	}
	
	/**
	 * Run parser.
	 */
	public function run()
	{
		$this->dbConnect();
	
		$this->getKmlFileContent();
		
		$polygons = $this->processPolygons();
		
		$this->savePoygons($polygons);
	}
	
	/**
	 * Connect to the DB.
	 */
	protected function dbConnect()
	{
		$this->db = new mysqli(static::DB_HOST, static::DB_USER, static::DB_PASSWORD, static::DB_NAME);
	}
	
	/**
	 * Read content from kml file.
	 */
	protected function getKmlFileContent()
	{
		$this->kmlContent = simplexml_load_file($this->kmlFileName);
	}
	
	/**
	 * Parse polygons.
	 * 
	 * @return array $polygons 
	 */
	protected function processPolygons()
	{
		$result = array();
	
		foreach ($this->kmlContent->Document->Folder->Placemark as $placemark)
		{
			$coordinates = array();
			
			//polygons could be multiple. look for tag MultiGeometry.
			if ($this->isMultiGeometryPlacemark($placemark))
			{
				
				foreach ($placemark->MultiGeometry->Polygon as $polygon)
				{
					$coordinates[] = $this->parsePolygonCoordinates($polygon);
				}
			}
			else
			{
				$coordinates[] =  $this->parsePolygonCoordinates($placemark->Polygon);
			}
			
			$result[] = array(
					'name' => $placemark->name,
					'coordinates' => $coordinates,
			);
		}
		
		return $result;
	}
	
	/**
	 * Parse polygon coordinates.
	 * 
	 * @param \SimpleXMLElement $polygon
	 * @return array $coordinates
	 */
	protected function parsePolygonCoordinates(\SimpleXMLElement $polygon)
	{
		$outerCoordinates = $this->getCoordinates($polygon->outerBoundaryIs->LinearRing->coordinates);
		$innerCoordinates = array();
		
		// innerBoundaryIs could have multiply LinearRings.
		// let's get them all!
		if (!empty($polygon->innerBoundaryIs))
		{
			foreach ($polygon->innerBoundaryIs->LinearRing as $linearRing)
			{
				$innerCoordinates[] = $this->getCoordinates($linearRing->coordinates);
			}
		}
		
		return array(
			'outerCoordinates' => $outerCoordinates,
			'innerCoordinates' => $innerCoordinates
		);
	}
	
	/**
	 * Get coordinates from the source.
	 * 
	 * @param \SimpleXMLElement $coordinatesSource
	 * @return array $coordinates
	 */
	protected function getCoordinates(\SimpleXMLElement $coordinatesSource)
	{
		$parsedCoordinate = explode(' ', $coordinatesSource);
		
		$result = array();
		foreach ($parsedCoordinate as $coordinates)
		{
			$lngLat = explode(',', $coordinates);
			
			if (empty($lngLat[0]) || empty($lngLat[1]))
			{
				continue;
			}
		
			$result[] = trim($lngLat[0]) . ' ' . trim($lngLat[1]);
		}
		return $result;
	}
	
	/**
	 * Saves polygons
	 * 
	 * @param array $polygons
	 */
	protected function savePoygons($polygons)
	{
		foreach ($polygons as $polygon)
		{
			$queries = $this->prepareDatabaseQuery($polygon);
			
			$this->query($queries);
		}
	}
	
	/**
	 * @param array $polygon
	 * @return array 
	 */
	protected function prepareDatabaseQuery(array $polygon)
	{
		$query = "SET @g = 'POLYGON";
		
		foreach($polygon['coordinates'] as $coordinates)
		{
			$query .= '((' . implode(',', $coordinates['outerCoordinates']);
		
			// innerBoundaryIs could have multiply LinearRings
			if (!empty($coordinates['innerCoordinates']))
			{
				// closing outer coordinates
				$query .= '),';
		
				foreach ($coordinates['innerCoordinates'] as $key => $innerCoordinates)
				{
					$query .= '(' . implode(',', $innerCoordinates) . ')';
					
					if ($key + 1 != count($coordinates['innerCoordinates']))
					{
						$query .= ',';
					}
				}
				
				$query .= ')';
			}
			else 
			{
				$query .= ')); ';
			}
		}
		
		$query .= "';\n";

		return array ($query , 'INSERT INTO regions (`name`, `polygons`) VALUES (' . $polygon["name"] . ', GeomFromText(@g))');
	}
	
	/**
	 * @param array $queries
	 */
	protected function query(array $queries)
	{
		foreach ($queries as $query)
		{
			$this->db->query($query);
			
			if (!empty($this->db->error))
			{
				// Mysql error happened
				echo "Error happened on query \"" . $query . "\"\n Error text:";
				exit($this->db->error);
			}
		}
	}
	
	/**
	 * @param \SimpleXMLElement $placemark
	 * @return boolean
	 */
	protected function isMultiGeometryPlacemark(\SimpleXMLElement $placemark)
	{
		return !empty($placemark->MultiGeometry);
	}
	
	public function __destruct()
	{
		$this->db->close();
		exit("\n done.");
	}
}