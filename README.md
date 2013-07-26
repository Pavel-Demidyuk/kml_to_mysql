kml_to_mysql
============

PHP: KML to Mysql

Basically all you need is just setup file name and credentials to your mysql database in parser.php.

Mysql table:
```sql
--
-- Table structure for table `regions`
--
CREATE TABLE IF NOT EXISTS `regions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8_unicode_ci NOT NULL,
  `polygons` polygon NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
--
```
I've tested this on regions of Mother Russia, see it in regions2010_wgs.KML (downloaded from http://gis-lab.info/qa/rusbounds-rosreestr.html)

Script createed for http://keytoday.net.