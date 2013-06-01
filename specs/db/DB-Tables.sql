
# devel Dragon Go Server dump
# Host: dragongoserver.sourceforge.net
# Database: d29933_dragongoserver
# Generation Time: 2007-02-04 23:41:04 GMT
# Server version: Apache/1.3.33 (Unix) PHP/4.3.10
# PHP version: 4.3.10
# MySQL version: 4.1.21-standard-log


#
# Table structure for table 'Players'
#

-- Players contain is the table containing ALL information and configuration for a player.
-- Players is often read at start of page into global var to have user-specific details in page.
CREATE TABLE Players (
   ID int(11) NOT NULL auto_increment, -- numerical user-id 'uid'
   Handle varchar(16) NOT NULL default '', -- textual user-handle
   Password varchar(41) default NULL, -- crypted password, created with mysql password('password')
   Newpassword varchar(41) default NULL, -- ??
   Sessioncode varchar(41) default NULL, -- ??
   Sessionexpire datetime default NULL, -- ??
   Lastaccess datetime default NULL, -- on every viewing of a page on DGS, this is updated to the current-time
   LastMove datetime default NULL, -- updated to current-time when players moves in a game
   Registerdate date default NULL, -- datetime when user first created the account on DGS
   Hits int(11) default '0', -- value increased by one, when visiting or reload a page (is_logged_in-check)
   Moves int(11) default '0', -- counter for made moves
   Activity double NOT NULL default '15', -- acitivity points, certain activities on the server adds to this (page-visit, making-move); value is decreased by cron-scripts (half-time)
   Name varchar(40) NOT NULL default '', -- full name of player
   Email varchar(80) default NULL, -- email of player
   Rank varchar(40) default NULL, -- textual rank-information (free text, no meaning for rating)
   Stonesize tinyint(3) unsigned NOT NULL default '25', -- CONF: stone-size used to select images ??
   SendEmail set('ON','MOVE','BOARD','MESSAGE') NOT NULL default '', -- CONF: ??
   Notify enum('NONE','NEXT','NOW','DONE') NOT NULL default 'NONE', -- CONF: ??
   MenuDirection enum('VERTICAL','HORIZONTAL') NOT NULL default 'VERTICAL', -- CONF: ??
   Adminlevel int(11) NOT NULL default '0', -- bit-mask for different admin-tasks, -1=full-fledged-admin
   Timezone varchar(40) NOT NULL default 'GMT', -- CONF: ??
   Nightstart int(11) NOT NULL default '22', -- CONF: ??
   ClockUsed int(11) NOT NULL default '22', -- ??
   ClockChanged enum('N','Y') NOT NULL default 'Y', -- ??
   Rating double default NULL, -- ??
   Rating2 double default NULL, -- current rating (EGF-rating in range 100 - ), used for calculations to start game
   RatingMin double default NULL, -- minimal rating (for rating-graph), + ??
   RatingMax double default NULL, -- maximum rating (for rating-graph), + ??
   InitialRating double default NULL, -- ??
   RatingStatus enum('INIT','RATED') default NULL, -- INIT=no-rating-set, RATED=rating-set
   Open varchar(40) default NULL, -- ??
   Lang varchar(20) NOT NULL default 'C', -- ??
   VacationDays double default '14', -- ??
   OnVacation double default '0', -- ??
   Woodcolor int(11) NOT NULL default '1', -- CONF: ??
   Boardcoords int(11) NOT NULL default '31', -- CONF: ??
   MoveNumbers smallint(5) unsigned NOT NULL default '0', -- CONF: ??
   MoveModulo smallint(5) unsigned NOT NULL default '0', -- ??
   Button int(11) NOT NULL default '0', -- ??
   UsersColumns int(10) unsigned NOT NULL default '62', -- bit-mask representing shown columns for page 'users.php'
   GamesColumns int(10) unsigned NOT NULL default '593910', -- bit-mask representing shown columns for page 'status.php'
   RunningGamesColumns int(10) unsigned NOT NULL default '593910', -- bit-mask representing shown columns for page 'show_games.php' (running-games)
   FinishedGamesColumns int(10) unsigned NOT NULL default '593910', -- bit-mask representing shown columns for page 'show_games.php' (finished-games)
   ObservedGamesColumns int(10) unsigned NOT NULL default '593910', -- bit-mask representing shown columns for page 'show_games.php' (observed-games)
   TournamentsColumns int(10) unsigned NOT NULL default '62', -- bit-mask representing shown columns, (unused)
   WaitingroomColumns int(10) unsigned NOT NULL default '253', -- bit-mask representing shown columns for page 'waiting_room.php'
   StatusFolders varchar(40) NOT NULL default '', -- ??
   Running int(11) NOT NULL default '0', -- number of running games for player
   Finished int(11) NOT NULL default '0', -- number of finished games for player
   RatedGames int(11) NOT NULL default '0', -- number of finished rated games for player
   Won int(11) NOT NULL default '0', -- number of won games for player
   Lost int(11) NOT NULL default '0', -- number of lost games for player
   Translator varchar(80) NOT NULL default '', -- ??
   IP varchar(16) NOT NULL default '', -- ??
   Browser varchar(100) default NULL, -- ??
   Country char(2) default NULL, -- CONF: country for player, for codes see 'include/countries.php'
   NotesSmallHeight tinyint(3) unsigned NOT NULL default '25', -- CONF: ??
   NotesSmallWidth tinyint(3) unsigned NOT NULL default '30', -- CONF: ??
   NotesSmallMode enum('RIGHT','BELOW','RIGHTOFF','BELOWOFF') NOT NULL default 'RIGHT', -- CONF: ??
   NotesLargeHeight tinyint(3) unsigned NOT NULL default '25', -- CONF: ??
   NotesLargeWidth tinyint(3) unsigned NOT NULL default '30', -- CONF: ??
   NotesLargeMode enum('RIGHT','BELOW','RIGHTOFF','BELOWOFF') NOT NULL default 'RIGHT', -- CONF: ??
   NotesCutoff tinyint(3) unsigned NOT NULL default '13', -- CONF: ??
   SkinName varchar(32) NOT NULL default '', -- CONF: used skin for CSS
   PRIMARY KEY  (ID),
   UNIQUE KEY Handle (Handle),
   KEY Rating2 (Rating2),
   KEY Name (Name),
   KEY Activity (Activity)
) TYPE=MyISAM;

#
# Table structure for table 'Games'
#

-- table containg all finished and currently running games
CREATE TABLE Games (
   ID int(11) NOT NULL auto_increment, -- 'gid'
   Starttime datetime NOT NULL default '0000-00-00 00:00:00', -- ??
   Lastchanged datetime NOT NULL default '0000-00-00 00:00:00', -- ??
   mid int(11) NOT NULL default '0', -- ??
   Tournament_ID int(11) NOT NULL default '0', -- ??
   Black_ID int(11) NOT NULL default '0', -- black-player, FK to Players.ID
   White_ID int(11) NOT NULL default '0', -- white-player, FK to Players.ID
   ToMove_ID int(11) NOT NULL default '0', -- player to move next, FK to Players.ID
   Size int(11) NOT NULL default '19', -- game-conf: board-size, e.g. 9, 19, etc.
   Komi decimal(6,1) NOT NULL default '6.5', -- game-conf: komi
   Handicap int(11) NOT NULL default '0', -- game-conf: number of handicap stones
   Status enum('INVITED','PLAY','PASS','SCORE','SCORE2','FINISHED') NOT NULL default 'INVITED', -- current status of game (enum)
   Moves int(11) NOT NULL default '0', -- number of moves made so far for game
   Black_Prisoners int(11) NOT NULL default '0', -- number of captured (W) stones for black-player
   White_Prisoners int(11) NOT NULL default '0', -- number of captured (B) stones for white-player
   Last_X int(11) NOT NULL default '-1', -- ??
   Last_Y int(11) NOT NULL default '-1', -- ??
   Last_Move char(2) NOT NULL default '', -- ??
   Flags set('Ko') NOT NULL default '', -- ??
   Score decimal(7,1) NOT NULL default '0.0', -- ??
   Maintime int(11) NOT NULL default '0', -- main-time for all used time-system specified in [hours] ?
   Byotype enum('JAP','CAN','FIS') NOT NULL default 'JAP', -- time-system used: JAP=japanese, CAN=canadian, FIS=Fisher-time
   Byotime int(11) NOT NULL default '0', -- ??
   Byoperiods int(11) NOT NULL default '0', -- ??
   Black_Maintime int(11) NOT NULL default '0', -- current available main-time for black-player
   White_Maintime int(11) NOT NULL default '0', -- current available main-time for white-player
   Black_Byotime int(11) NOT NULL default '0', -- ??
   White_Byotime int(11) NOT NULL default '0', -- ??
   Black_Byoperiods int(11) NOT NULL default '-1', -- ??
   White_Byoperiods int(11) NOT NULL default '-1', -- ??
   LastTicks int(11) NOT NULL default '0', -- ??
   ClockUsed int(11) NOT NULL default '0', -- each game has an assigned clock, FK to Clocks.ID
   Rated enum('N','Y','Done') NOT NULL default 'N', -- game-conf: indicating, if game is rated ('Y') or unrated ('N'), Done ? => A game start with the 'N' or 'Y' status. If it had the 'Y' status at the end, the Player_Rating/RatingLog/Stats stuff is managed and its status is set to 'Done' to avoid doing this stuff twice. So, when a game is finished it had either the 'N' or 'Done' status. 'Done' means 'Y'+recorded (at least, if a 'N' is not changed to 'Done' somewhere... but it should not). So a game status is either 'N' or not 'N' ;)
   StdHandicap enum('N','Y') NOT NULL default 'N', -- game-conf: 'Y', if standard-handicap-placement is used for game
   WeekendClock enum('N','Y') NOT NULL default 'Y', -- game-conf: ??
   Black_Start_Rating double NOT NULL default '-9999', -- rating black-player started game with
   White_Start_Rating double NOT NULL default '-9999', -- rating white-player started game with
   Black_End_Rating double NOT NULL default '-9999', -- rating of black-player after game finished
   White_End_Rating double NOT NULL default '-9999', -- rating of white-player after game finished
   PRIMARY KEY  (ID),
   KEY ToMove_ID (ToMove_ID),
   KEY Size (Size),
   KEY Lastchanged (Lastchanged),
   KEY Status (Status),
   KEY ClockUsed (ClockUsed),
   KEY Maintime (Maintime),
   KEY Byotime (Byotime),
   KEY Black_ID (Black_ID),
   KEY White_ID (White_ID)
) TYPE=MyISAM;

#
# Table structure for table 'GamesNotes'
#

CREATE TABLE GamesNotes (
   ID int(11) unsigned NOT NULL auto_increment, -- ??
   gid int(11) NOT NULL default '0', -- ??
   player enum('B','W') NOT NULL default 'B', -- ??
   Hidden enum('N','Y') NOT NULL default 'N', -- ??
   Notes text NOT NULL, -- ??
   PRIMARY KEY  (gid,player), -- ??
   KEY ID (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Bio'
#

-- biography entries, editable in user-info page
CREATE TABLE Bio (
   ID int(11) NOT NULL auto_increment,
   uid int(11) NOT NULL default '0', -- FK to Players.ID
   Category varchar(40) NOT NULL default '', -- category-name
   Text text NOT NULL, -- free text for category
   SortOrder int(11) NOT NULL default '0', -- sort-order used for displaying and changing order of entries
   PRIMARY KEY  (ID),
   KEY uid (uid)
) TYPE=MyISAM;

#
# Table structure for table 'RatingChange'
#

CREATE TABLE RatingChange (
   ID int(11) NOT NULL auto_increment, -- ??
   uid int(11) NOT NULL default '0', -- ??
   gid int(11) NOT NULL default '0', -- ??
   diff double default NULL, -- ??
   PRIMARY KEY  (ID), -- ??
   KEY uid (uid)
) TYPE=MyISAM;

#
# Table structure for table 'Ratinglog'
#

-- summary table of rated(?) games
CREATE TABLE Ratinglog (
   ID int(11) NOT NULL auto_increment,
   uid int(11) NOT NULL default '0', -- FK to Players.ID
   gid int(11) NOT NULL default '0', -- FK to Games.ID
   Time datetime NOT NULL default '0000-00-00 00:00:00', -- ??
   Rating double default NULL, -- ??
   RatingMin double default NULL, -- ??
   RatingMax double default NULL, -- ??
   RatingDiff double default NULL, -- difference in rating (EGF-rating), diff of '100' corresponds to 1 rank, displayed is 'diff/100' (1=1rank)
   PRIMARY KEY  (ID),
   KEY uid (uid),
   KEY gid (gid,uid)
) TYPE=MyISAM;

#
# Table structure for table 'Statistics'
#

-- summary-table, created once a day with cron ?
CREATE TABLE Statistics (
   ID int(11) NOT NULL auto_increment,
   Time datetime default NULL, -- ??
   Hits int(11) default NULL, -- ??
   Users int(11) default NULL, -- ??
   Moves int(11) default NULL, -- ??
   MovesFinished int(11) default NULL, -- ??
   MovesRunning int(11) default NULL, -- ??
   Games int(11) default NULL, -- ??
   GamesFinished int(11) default NULL, -- ??
   GamesRunning int(11) default NULL, -- ??
   Activity int(11) default NULL, -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Messages'
#

-- game-messages, system-messages and messages send between players/system
CREATE TABLE Messages (
   ID int(11) NOT NULL auto_increment, -- message-ID 'mid'
   Type enum('NORMAL','INVITATION','ACCEPTED','DECLINED','DELETED','DISPUTED','RESULT') NOT NULL default 'NORMAL', -- ??
   ReplyTo int(11) NOT NULL default '0', -- FK to other Messages.ID (as reply)
   Game_ID int(11) NOT NULL default '0', -- message related to game
   Time datetime NOT NULL default '0000-00-00 00:00:00', -- ??
   Subject varchar(80) NOT NULL default '', -- subject of message
   Text text NOT NULL, -- main-text (body) of message
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'MessageCorrespondents'
#

CREATE TABLE MessageCorrespondents (
   ID int(11) NOT NULL auto_increment, -- ??
   uid int(11) NOT NULL default '0', -- ??
   mid int(11) NOT NULL default '0', -- ??
   Folder_nr int(11) default NULL, -- ??
   Sender enum('M','N','Y') NOT NULL default 'Y', -- 'M'=myself(?)
   Replied enum('M','N','Y') NOT NULL default 'N', -- 'M'=myself(?)
   PRIMARY KEY  (ID), -- ??
   KEY mid (mid), -- ??
   KEY uid (uid), -- ??
   KEY Folder_nr (Folder_nr,uid)
) TYPE=MyISAM;

#
# Table structure for table 'Folders'
#

CREATE TABLE Folders (
   ID int(11) NOT NULL auto_increment, -- ??
   uid int(11) NOT NULL default '0', -- ??
   Folder_nr int(11) NOT NULL default '0', -- ??
   Name varchar(40) NOT NULL default '', -- ??
   BGColor varchar(8) NOT NULL default 'f7f5e3FF', #skin compatibility: Ok, enought contrast
   FGColor varchar(6) NOT NULL default '000000', -- ??
   PRIMARY KEY  (uid,Folder_nr), -- ??
   KEY ID (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Moves'
#

CREATE TABLE Moves (
   ID int(11) NOT NULL auto_increment, -- ??
   gid int(11) NOT NULL default '0', -- ??
   MoveNr smallint(5) unsigned NOT NULL default '0', -- ??
   Stone tinyint(3) unsigned NOT NULL default '0', -- ??
   PosX tinyint(4) NOT NULL default '-128', -- ??
   PosY tinyint(4) NOT NULL default '-128', -- ??
   Hours smallint(5) unsigned default NULL, -- ??
   PRIMARY KEY  (ID), -- ??
   KEY gid (gid,ID)
) TYPE=MyISAM;

#
# Table structure for table 'MoveMessages'
#

CREATE TABLE MoveMessages (
   ID int(11) unsigned NOT NULL auto_increment, -- ??
   gid int(11) NOT NULL default '0', -- ??
   MoveNr smallint(5) unsigned NOT NULL default '0', -- ??
   Text text, -- ??
   PRIMARY KEY  (gid,MoveNr), -- ??
   KEY ID (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Waitingroom'
#

-- game-offers, showed in waiting-room
CREATE TABLE Waitingroom (
   ID int(11) NOT NULL auto_increment,
   uid int(11) NOT NULL default '0', -- player offering games, FK to Players.ID
   nrGames int(11) NOT NULL default '1', -- number of games with game-conf of this entry
   Time datetime NOT NULL default '0000-00-00 00:00:00', -- ??
   Size int(11) NOT NULL default '19', -- board-size
   Komi decimal(6,1) NOT NULL default '6.5', -- komi
   Handicap int(11) NOT NULL default '0', -- number of handicap stones
   Handicaptype enum('conv','proper','nigiri','double') NOT NULL default 'conv', -- offered handicap-type (conv=conventional, double=double-game)
   Maintime int(11) NOT NULL default '0', -- provided main-time
   Byotype enum('JAP','CAN','FIS') NOT NULL default 'JAP', -- time-system used (see Games.Byotype)
   Byotime int(11) NOT NULL default '0', -- ??
   Byoperiods int(11) NOT NULL default '0', -- ??
   Rated enum('N','Y','Done') NOT NULL default 'N', -- offer as rated game?, 'Done' ?
   StdHandicap enum('N','Y') NOT NULL default 'N', -- game-conf: standard-handicap should be used, if possible
   WeekendClock enum('N','Y') NOT NULL default 'Y', -- ??
   MustBeRated enum('N','Y','Done') NOT NULL default 'N', -- game-conf: expecting rated opponent
   RatingMin double NOT NULL default '-9999', -- minimum rating opponent must have, ?=no-min
   RatingMax double NOT NULL default '-9999', -- maximum rating opponent must have, ?=no-max
   Comment varchar(40) NOT NULL default '', -- game-offer comment
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Observers'
#

-- player/game-matrix to mark games as 'observed' by specific player
CREATE TABLE Observers (
   ID int(11) NOT NULL auto_increment,
   uid int(11) default NULL, -- observing player, FK to Players.ID
   gid int(11) default NULL, -- observed game, FK to Games.ID
   PRIMARY KEY  (ID),
   KEY uid (uid),
   KEY gid (gid)
) TYPE=MyISAM;

#
# Table structure for table 'Tournament'
#

-- from 2002: attempt to implement tournaments
CREATE TABLE Tournament (
   ID int(11) NOT NULL auto_increment, -- ??
   Name varchar(80) default NULL, -- ??
   Description text, -- ??
   State int(11) NOT NULL default '0', -- ??
   FirstRound int(11) default NULL, -- ??
   ApplicationPeriod int(11) default NULL, -- ??
   StartOfApplicationPeriod datetime default NULL, -- ??
   StrictEndOfApplicationPeriod enum('N','Y') NOT NULL default 'N', -- ??
   ReceiveApplicationsAfterStart enum('N','Y') NOT NULL default 'N', -- ??
   MinParticipants int(11) NOT NULL default '2', -- ??
   MaxParticipants int(11) default NULL, -- ??
   Rated enum('N','Y') NOT NULL default 'N', -- ??
   WeekendClock enum('N','Y') NOT NULL default 'Y', -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'TournamentRound'
#

-- from 2002: attempt to implement tournaments
CREATE TABLE TournamentRound (
   ID int(11) NOT NULL auto_increment, -- ??
   TournamentID int(11) default NULL, -- ??
   NextRound int(11) default NULL, -- ??
   PreviousRound int(11) default NULL, -- ??
   BoardSize int(11) default '19', -- ??
   Komi decimal(6,1) default '6.5', -- ??
   HandicapType int(11) NOT NULL default '0', -- ??
   Maintime int(11) default NULL, -- ??
   Byotype enum('JAP','CAN','FIS') NOT NULL default 'JAP', -- ??
   Byotime int(11) NOT NULL default '0', -- ??
   Byoperiods int(11) NOT NULL default '0', -- ??
   GamesPerpair int(11) NOT NULL default '1', -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'TournamentOrganizers'
#

-- from 2002: attempt to implement tournaments
CREATE TABLE TournamentOrganizers (
   ID int(11) NOT NULL auto_increment, -- ??
   tid int(11) NOT NULL default '0', -- ??
   pid int(11) NOT NULL default '0', -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'TournamentParticipants'
#

-- from 2002: attempt to implement tournaments
CREATE TABLE TournamentParticipants (
   ID int(11) NOT NULL auto_increment, -- ??
   tid int(11) NOT NULL default '0', -- ??
   pid int(11) NOT NULL default '0', -- ??
   Seeding int(11) NOT NULL default '0', -- ??
   PlayerNumber int(11) default NULL, -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Errorlog'
#

-- error-log written on database-error with some info given in the code
-- example-usage: error('mysql_query_failed', 'users.find_data');
CREATE TABLE Errorlog (
   ID int(11) NOT NULL auto_increment,
   Handle varchar(16) NOT NULL default '', -- ??
   Message text NOT NULL, -- ??
   MysqlError text NOT NULL, -- ??
   Debug text NOT NULL, -- ??
   IP varchar(16) NOT NULL default '', -- ??
   Date timestamp(14) NOT NULL, -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Adminlog'
#

CREATE TABLE Adminlog (
   ID int(11) NOT NULL auto_increment, -- ??
   uid int(11) NOT NULL default '0', -- ??
   Handle varchar(16) NOT NULL default '', -- ??
   Message text NOT NULL, -- ??
   Date timestamp(14) NOT NULL, -- ??
   IP varchar(16) NOT NULL default '', -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Translationlog'
#

CREATE TABLE Translationlog (
   ID int(11) NOT NULL auto_increment, -- ??
   Player_ID int(11) default NULL, -- ??
   Language_ID int(11) default NULL, -- ??
   Original_ID int(11) default NULL, -- ??
   Handle varchar(16) default NULL, -- ??
   Language varchar(16) default NULL, -- ??
   CString text NOT NULL, -- ??
   OldTranslation blob NOT NULL, -- ??
   Translation blob NOT NULL, -- ??
   Date timestamp(14) NOT NULL, -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'TranslationTexts'
#

CREATE TABLE TranslationTexts (
   ID int(11) NOT NULL auto_increment, -- ??
   Text blob NOT NULL, -- ??
   Ref_ID int(11) default NULL, -- ??
   Translatable enum('Y','N','Done','Changed') NOT NULL default 'Y', -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Translations'
#

CREATE TABLE Translations (
   Original_ID int(11) NOT NULL default '0', -- ??
   Language_ID int(11) NOT NULL default '0', -- ??
   Text blob NOT NULL, -- ??
   PRIMARY KEY  (Language_ID,Original_ID)
) TYPE=MyISAM;

#
# Table structure for table 'TranslationLanguages'
#

-- available languages that need translations
CREATE TABLE TranslationLanguages (
   ID int(11) NOT NULL auto_increment,
   Language varchar(32) default NULL, -- ??
   Name varchar(32) default NULL, -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'TranslationGroups'
#

-- each php-page can be assigned a single TranslationGroup, so that texts on different pages
-- can have different translations depending on the context.
CREATE TABLE TranslationGroups (
   ID int(11) NOT NULL auto_increment,
   Groupname varchar(32) NOT NULL default '', -- name of group, see scripts/README.translations
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'TranslationFoundInGroup'
#

CREATE TABLE TranslationFoundInGroup (
   Text_ID int(11) NOT NULL default '0', -- ??
   Group_ID int(11) NOT NULL default '0', -- ??
   PRIMARY KEY  (Text_ID,Group_ID)
) TYPE=MyISAM;

#
# Table structure for table 'TranslationPages'
#

-- list of php-pages, that need to be translated (updated by a script)
-- see scripts/README.translations
CREATE TABLE TranslationPages (
   ID int(11) NOT NULL auto_increment,
   Page varchar(64) default NULL, -- relative filename of php-page, which has text to be translated
   Group_ID int(11) default NULL, -- belonging group, FK to TranslationGroups.ID
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Forums'
#

-- list of Forums
CREATE TABLE Forums (
   ID int(11) NOT NULL auto_increment,
   Name varchar(40) default NULL, -- name of forum
   Description varchar(255) default NULL, -- description of forum
   SortOrder int(11) NOT NULL default '0', -- display-order of forums
   Moderated char(1) NOT NULL default 'N', -- indicating, if forum is moderated ('Y')
   LastPost int(11) default NULL, -- ??
   PostsInForum int(11) NOT NULL default '0', -- number of posts in forum
   PRIMARY KEY  (ID),
   KEY SortOrder (SortOrder)
) TYPE=MyISAM;

#
# Table structure for table 'Posts'
#

-- post-messages entered in forum
-- Threads are built with Thread_ID to other posts-message, Thread_ID=0 indicates the initial-thread-post
CREATE TABLE Posts (
   ID int(11) NOT NULL auto_increment,
   Forum_ID int(11) NOT NULL default '0', -- corresponding forum, FK to Forum.ID
   Time datetime NOT NULL default '0000-00-00 00:00:00', -- datetime, when post is created
   Lastchanged datetime NOT NULL default '0000-00-00 00:00:00', -- datetime of last change of this post
   Lastedited datetime NOT NULL default '0000-00-00 00:00:00', -- datetime of last edit of post, Revision history of posts is kept by copying Post
   Subject varchar(80) NOT NULL default '', -- subject of post/thread
   Text text NOT NULL, -- text of post, can be searched with full-text-mysql-match
   User_ID int(11) NOT NULL default '0', -- author, FK to Players.ID
   Parent_ID int(11) NOT NULL default '0', -- ??
   Thread_ID int(11) NOT NULL default '0', -- ??
   AnswerNr int(11) NOT NULL default '0', -- ??
   Depth int(11) NOT NULL default '0', -- ??
   crc32 int(11) default NULL, -- ??
   PosIndex varchar(80) binary NOT NULL default '', -- used to order messages, has some limitations (but in reality not reached), uses 2-char-blocks to sort even the hierarchy ??
   old_ID int(11) NOT NULL default '0', -- ??
   Approved enum('Y','N') NOT NULL default 'Y', -- post only displayed, if 'Y' (relevant in moderated forum)
   PostsInThread int(11) NOT NULL default '0', -- number of posts in thread, only set for initial thread-message (with Thread_ID=0)
   LastPost int(11) NOT NULL default '0', -- last post made, FK to Posts.ID
   PendingApproval enum('Y','N') NOT NULL default 'N', -- ??
   PRIMARY KEY  (ID),
   KEY Pos (Thread_ID,PosIndex),
   KEY List (Forum_ID,Lastchanged),
   KEY PendingApproval (PendingApproval,Time DESC),
   FULLTEXT KEY Subject (Subject,Text)
) TYPE=MyISAM;

#
# Table structure for table 'Forumreads'
#

-- used to maintain which forum-posts already read, table cleaned up by cron
CREATE TABLE Forumreads (
   User_ID int(11) NOT NULL default '0', -- ??
   Thread_ID int(11) NOT NULL default '0', -- ??
   Time datetime default NULL, -- ??
   PRIMARY KEY  (User_ID,Thread_ID)
) TYPE=MyISAM;

#
# Table structure for table 'GoDiagrams'
#

CREATE TABLE GoDiagrams (
   ID int(11) NOT NULL auto_increment, -- ??
   Size int(11) default NULL, -- ??
   View_Left int(11) default NULL, -- ??
   View_Right int(11) default NULL, -- ??
   View_Up int(11) default NULL, -- ??
   View_Down int(11) default NULL, -- ??
   Date datetime NOT NULL default '0000-00-00 00:00:00', -- ??
   Saved enum('Y','N') NOT NULL default 'N', -- ??
   Data text NOT NULL, -- ??
   SGF text NOT NULL, -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'FAQ'
#

CREATE TABLE FAQ (
   ID int(11) NOT NULL auto_increment, -- ??
   Parent int(11) NOT NULL default '0', -- ??
   Level int(11) NOT NULL default '0', -- ??
   SortOrder int(11) NOT NULL default '0', -- ??
   Question int(11) NOT NULL default '0', -- ??
   Answer int(11) NOT NULL default '0', -- ??
   Hidden enum('N','Y') NOT NULL default 'N', -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'FAQlog'
#

CREATE TABLE FAQlog (
   ID int(11) NOT NULL auto_increment, -- ??
   uid int(11) NOT NULL default '0', -- ??
   FAQID int(11) NOT NULL default '0', -- ??
   Question text, -- ??
   Answer text, -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM;

#
# Table structure for table 'Clock'
#

-- table for maintaining clocks
-- the daily_cron.php, halfhourly_cron.php and clock_tick.php are updating the different clocks
-- each running game has a clock assigned (Games.ClockUsed)
-- are clocks removed for finished games?
-- problem: when the cron is not running, the clocks aren't changed
CREATE TABLE Clock (
   ID int(11) NOT NULL default '0',
   Ticks int(11) default '0', -- ??
   Lastchanged datetime NOT NULL default '0000-00-00 00:00:00', -- ??
   PRIMARY KEY  (ID)
) TYPE=MyISAM PACK_KEYS=1;

#
# Dumping data for table 'Clock'
#

-- Clock.ID= -101 (vacation-clock: no real clock-entry in table)
-- Clock.ID=0..23: clock for each standard hour (0-23)
INSERT INTO Clock SET ID=0;
INSERT INTO Clock SET ID=23;
-- Clock.ID=100..123: clock for each standard hour (0-23) using weekend-clock
INSERT INTO Clock SET ID=100;
INSERT INTO Clock SET ID=123;
-- Clock.ID=201: used by 'clock_tick.php', cron called every 5mins
INSERT INTO Clock SET ID=201,Lastchanged=0;
-- Clock.ID=202: used by 'halfhourly_cron.php', cron called every 30mins
INSERT INTO Clock SET ID=202,Lastchanged=0;
-- Clock.ID=203: used by 'daily_cron.php', cron called once a day (normally at 05:25 server-time, see INSTALL)
INSERT INTO Clock SET ID=203,Lastchanged=0;

# about Clocks:
   Anyway, the "clock" system is what I've found when I've joined the DGS
   team on 2003. I've already tuned it a little but not
   modified the principle. I've also highlighted some minor
   defects.
   What I can say about it is:
   1) the "clock" word is not really good. The system acts more
   as a "tick counter"
   2) the main worry was to build a system that does not alter
   the games timing in case of a server shutdown. The worst
   thing allowed to happen is a clock freeze for ALL players.
   That's why:
   - the trigger stuff is on an other server (see the install
   recommendations). So, if the samuraj Net connection is
   broken, the clocks are frozen and no games will timeout.
   - any other shutdowns (maintenance, failure,...) are easily
   converted to games adjournments
   - because of those adjournments, the DGS clock system must
   be independent of the samuraj system clock... else at the
   end of the adjournment you will have a bunch of game timeouts.
   3) the next worry is to manage the various timezones and
   night-times in an easy way. Each player - and then, each
   game - simply receive one of our 24 counters depending of
   those parameters. A particular counter is incremented only
   15 hours per days. The weekend counters are not incremented
   during the weekends.

   The defect I've spotted is just that, depending of various
   settings' parameters, the weekend can start from friday
   12h00 to saturday 12h00, and end from sunday 12h00 to monday
   12h00. But that's not really a problem because each player
   always had exactly 2 days per week of weekend period...
   around saturday/sunday.


