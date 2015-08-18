-- phpMyAdmin SQL Dump
-- version 4.2.12deb2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 18, 2015 at 10:26 AM
-- Server version: 5.5.44-0+deb8u1-log
-- PHP Version: 5.6.9-0+deb8u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `rooster1516`
--

-- --------------------------------------------------------

--
-- Table structure for table `entities2events`
--

CREATE TABLE IF NOT EXISTS `entities2events` (
`entities2events_id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=59 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE IF NOT EXISTS `events` (
`event_id` int(11) NOT NULL,
  `start_week_id` int(11) NOT NULL,
  `start_dag` int(11) NOT NULL,
  `start_uur` int(11) NOT NULL,
  `eind_week_id` int(11) NOT NULL,
  `eind_dag` int(11) NOT NULL,
  `eind_uur` int(11) NOT NULL,
  `beschrijving` varchar(128) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `entities2events`
--
ALTER TABLE `entities2events`
 ADD PRIMARY KEY (`entities2events_id`), ADD UNIQUE KEY `entity_id` (`entity_id`,`event_id`), ADD KEY `entity_id2` (`entity_id`), ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
 ADD PRIMARY KEY (`event_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `entities2events`
--
ALTER TABLE `entities2events`
MODIFY `entities2events_id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=59;
--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=6;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
