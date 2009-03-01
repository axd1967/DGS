-- phpMyAdmin SQL Dump
-- version 2.11.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 07, 2009 at 11:55 PM
-- Server version: 5.0.51
-- PHP Version: 5.2.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `d29933_dragongoserver`
--

-- --------------------------------------------------------

--
-- Table structure for table `Knockout`
--

CREATE TABLE `Knockout` (
  `Tournament_ID` int(11) NOT NULL default '0',
  `Seedings` int(11) NOT NULL default '0',
  `UseHandicap` enum('Y','N') NOT NULL default 'Y',
  PRIMARY KEY  (`Tournament_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `Knockout`
--


-- --------------------------------------------------------

--
-- Table structure for table `Tournament`
--

CREATE TABLE `Tournament` (
  `ID` int(11) NOT NULL auto_increment,
  `Name` varchar(80) default NULL,
  `Description` text,
  `State` int(11) NOT NULL default '0',
  `FirstRound` int(11) default NULL,
  `ApplicationPeriod` int(11) default NULL,
  `StartOfApplicationPeriod` datetime default NULL,
  `StrictEndOfApplicationPeriod` enum('N','Y') NOT NULL default 'N',
  `ReceiveApplicationsAfterStart` enum('N','Y') NOT NULL default 'N',
  `MinParticipants` int(11) NOT NULL default '2',
  `MaxParticipants` int(11) default NULL,
  `Rated` enum('N','Y') NOT NULL default 'N',
  `WeekendClock` enum('N','Y') NOT NULL default 'Y',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `Tournament`
--


-- --------------------------------------------------------

--
-- Table structure for table `TournamentOrganizers`
--

CREATE TABLE `TournamentOrganizers` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL default '0',
  `pid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `TournamentOrganizers`
--


-- --------------------------------------------------------

--
-- Table structure for table `TournamentParticipants`
--

CREATE TABLE `TournamentParticipants` (
  `ID` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL default '0',
  `pid` int(11) NOT NULL default '0',
  `Seeding` int(11) NOT NULL default '0',
  `PlayerNumber` int(11) default NULL,
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `TournamentParticipants`
--


-- --------------------------------------------------------

--
-- Table structure for table `TournamentRound`
--

CREATE TABLE `TournamentRound` (
  `ID` int(11) NOT NULL auto_increment,
  `TournamentID` int(11) default NULL,
  `NextRound` int(11) default NULL,
  `PreviousRound` int(11) default NULL,
  `BoardSize` int(11) default '19',
  `Komi` decimal(6,1) default '6.5',
  `HandicapType` int(11) NOT NULL default '0',
  `Maintime` int(11) default NULL,
  `Byotype` enum('JAP','CAN','FIS') NOT NULL default 'JAP',
  `Byotime` int(11) NOT NULL default '0',
  `Byoperiods` int(11) NOT NULL default '0',
  `GamesPerpair` int(11) NOT NULL default '1',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `TournamentRound`
--


