<?php

class bsLikiBackend {
  var $dbh;
  var $database;
  var $dbUser = false;
  var $dbPassword = false;
  var $tablePrefix = false;
  var $pagesTable = false;
  var $revsTable = false;
  var $dbPersistent = false;

  function bsLikiBackend($handle_ = false) {
    global $bs_configpath;
    
    if ($handle_ === false) {
      require("config.php");

      if ($this->pagesTable === false) {
        $this->pagesTable = ($this->tablePrefix !== false ? $this->tablePrefix : '') . 'pages';
      }
      if ($this->revsTable === false) {
        $this->revsTable = ($this->tablePrefix !== false ? $this->tablePrefix : '') . 'revisions';
      }

      try {
        $this->dbh = new PDO($this->database, $this->dbUser, $this->dbPassword, array(
          PDO::ATTR_PERSISTENT => $this->dbPersistent));
      } catch (PDOException $e) {
        die('no connection to database possible: '.$e->getMessage());
      }
      if (strncmp($this->database, "sqlite:", 7) === 0) {
        $this->dbh->exec("PRAGMA temp_store = MEMORY");
      }
    } else {
      $this->dbh = $handle_;
    }

    /*if (!$this->tablePresent($this->db_table, array('name', 'content', 'lockkey',
                             'timestamp_change', 'timestamp_lock', 'timestamp_visit'))) {
      $query = "CREATE TABLE `".$this->db_table."` (".
               " name             varchar(100)     NOT NULL default '',".
               " content          text             default NULL,".
               " lockkey          varchar(32)      default NULL,".
               " timestamp_visit  int(10) unsigned NOT NULL default 0,".
               " timestamp_change int(10) unsigned NOT NULL default 0,".
               " timestamp_lock   int(10) unsigned NOT NULL default 0,".
               " PRIMARY KEY(name)".
               ")";
      @mysql_query($query, $this->dbh) or die('could not create table '.$this->db_table);
    }
    if (!$this->tablePresent($this->db_table.'_backup',
                             array('id', 'name', 'content',
                                   'timestamp_opened', 'timestamp_closed'))) {
      $query = "CREATE TABLE `".$this->db_table."_backup` (".
               " id               int(10) unsigned NOT NULL auto_increment,".
               " name             varchar(100)     NOT NULL default '',".
               " content          text             default NULL,".
               " timestamp_opened int(10) unsigned NOT NULL default 0,".
               " timestamp_closed int(10) unsigned NOT NULL default 0,".
               " PRIMARY KEY(id)".
               ")";
      @mysql_query($query, $this->dbh) or die('could not create table '.$this->db_table."_backup");
    }*/
  }

  function tablePresent($tablename, $columns) {
    /*$res = mysql_query('DESC `'.$tablename.'`', $this->dbh);
    if ($res) {
      if (mysql_num_rows($res) >= count($columns)) {
        $cols = array();
        for ($i = 0; $i < mysql_num_rows($res); $i ++) {
          $row = mysql_fetch_array($res);
          $cols[] = $row[0];
        }
        mysql_free_result($res);
        foreach($columns as $col) {
          if (!in_array($col, $cols)) die("column $col missing");
        }
      } else {
        die("not enough columns in $tablename. table layout changed?");
      }
    } else {
      return false;
    }*/
    return true;
  }

  function createTables() {
    $schemas = array("mysql" => array(
            "pages" => "CREATE TABLE `{$this->pagesTable}` (".
                       " `id` int(10) unsigned NOT NULL auto_increment,".
                       " `name` varchar(100) NOT NULL,".
                       " `lockkey` varchar(32) character set ascii default NULL,".
                       " `timestamp_visit` int(10) unsigned default NULL,".
                       " `timestamp_lock` int(10) unsigned default NULL,".
                       " `revision_id` int(10) unsigned default NULL,".
                       " `has_changes` char(1) character set latin1 NOT NULL default 'N',".
                       " PRIMARY KEY  (`id`),".
                       " KEY `lockkey` USING BTREE (`lockkey`),".
                       " UNIQUE KEY `name` USING BTREE (`name`)".
                       ") ENGINE=InnoDB DEFAULT CHARSET=utf8",
            "revisions" => "CREATE TABLE `{$this->revsTable}` (".
                           " `id` int(10) unsigned NOT NULL auto_increment,".
                           " `page_id` int(10) unsigned NOT NULL,".
                           " `timestamp_change` int(10) unsigned default NULL,".
                           " `content` text,".
                           " `remote_ip` int(10) unsigned default NULL,".
                           " `remote_agent` varchar(200) default NULL,".
                           " PRIMARY KEY (`id`),".
                           " KEY `page_id` (`page_id`),".
                           " KEY `timestamp_change` (`timestamp_change`)".
                           ") ENGINE=InnoDB DEFAULT CHARSET=utf8"
          ),
          "sqlite" => array(
            "pages" => "CREATE TABLE {$this->pagesTable} (id INTEGER PRIMARY KEY, name, lockkey, timestamp_visit INTEGER, timestamp_lock INTEGER, revision_id INTEGER, has_changes)",
            "revisions" => "CREATE TABLE {$this->revsTable} (id INTEGER PRIMARY KEY, page_id INTEGER, timestamp_change INTEGER, content, remote_ip INTEGER, remote_agent)"
          )
    );
    $driver = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (array_key_exists($driver, $schemas)) {
      $this->dbh->beginTransaction();
      if ($this->dbh->exec($schemas[$driver]['pages']) === FALSE ||
          $this->dbh->exec($schemas[$driver]['revisions']) === FALSE) {
        $err = $this->dbh->errorInfo();
        error_log('createTables(): Unable to create pages table: '.$err[2]);
        $this->dbh->rollback();
        return false;
      }
      $this->dbh->commit();
      return true;
    }

    error_log('createTables(): Table creating not supported for your database type!');
    return false;
  }

  function cleanPageName($name) {
    return preg_replace('[\'\"\]\[\%\s]', '', $name);
  }

  function autoFree() {
    // TODO FIXME TODO FIXME TODO FIXME
    /* refactor: NEUE revision nur anlegen, bei aenderung. dadurch LOCK bei JEDEM update (auch erfolglos o. keine aenderung),
                 aber: _kein_ lock beim autofree -- seiten werden nur noch freigegeben, revisionen nicht angeruehrt! */
    $timestamp = time() - 180;
    // unlock pages
    $s = $this->dbh->prepare("UPDATE {$this->pagesTable} SET lockkey='', has_changes='N' WHERE ".
                             "(timestamp_lock < :timestamp) AND lockkey != ''");
    if (!$s) {
      $err = $this->dbh->errorInfo();
      error_log('autoFree(): '.$err[2]);
      return false;
    }
    $s->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
    if (!$s->execute()) {
      $err = $this->dbh->errorInfo();
      error_log('autoFree(): '.$err[2]);
      $s = null;
      return false;
    }

    $s = null;
    return true;
  }

  function lockPage($page, $key) {
    $this->autoFree();
    $timestamp = time();
    $n = 'N';
    $ip = sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
    $page = $this->cleanPageName($page);

    // first, try locking the page (and safe the old revision for later use)
    $this->dbh->beginTransaction();
    $s = $this->dbh->prepare("SELECT id, lockkey, timestamp_lock FROM {$this->pagesTable} WHERE name=:pagename");
    $s->bindParam(':pagename', $page, PDO::PARAM_STR);
    if ($s->execute()) {
      $res = $s->fetchAll();
      $s = null;
      if (count($res) == 0) {
        error_log("page does not exist.");
        $insert = $this->dbh->prepare("INSERT INTO {$this->pagesTable} (name, lockkey, timestamp_lock, has_changes) ".
                                      "VALUES (:name, :lockkey, :timestamp, :changes)");
        $insert->bindParam(':name', $page, PDO::PARAM_STR);
        $insert->bindParam(':lockkey', $key, PDO::PARAM_STR);
        $insert->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
        $insert->bindParam(':changes', $n, PDO::PARAM_STR);
        if ($insert->execute()) {
          error_log("page created AND LOCKED.");
          $insert = null;
          $this->dbh->commit();
          return true;
        }

        $insert = null;
        error_log('unable to create page.');
        $err = $this->dbh->errorInfo();
        error_log($err[2]);
        $this->dbh->rollback();
        return false;
      }

      error_log("page exists.");
      $row = $res[0];
      if ($row['lockkey'] == null || $row['lockkey'] == '') {
        error_log("page is not locked ATM.");
        $update = $this->dbh->prepare("UPDATE {$this->pagesTable} SET lockkey=:lockkey, timestamp_lock=:timestamp, has_changes=:changes ".
                                "WHERE id=:id");
        $update->bindParam(':id', $row['id'], PDO::PARAM_INT);
        $update->bindParam(':lockkey', $key, PDO::PARAM_STR);
        $update->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
        $update->bindParam(':changes', $n, PDO::PARAM_STR);
        if ($update->execute()) {
          if ($update->rowCount() == 1) {
            error_log("LOCKED PAGE.");
            $update = null;
            $this->dbh->commit();
            return true;
          }
          $update = null;
          error_log('lockPage(): Unable to lock page.');
        } else {
          $err = $this->dbh->errorInfo();
          error_log('lockPage(): Unable to lock page: '.$err[2]);
        }
        $update = null;
      } else {
        error_log('lockPage(): page locked for another '.
                  (180 - ($timestamp - $row['timestamp_lock'])) . ' seconds...');
      }
    }

    error_log('DID NOT GIVE LOCK!');
    $this->dbh->rollback();
    return false;
  }

  function freePage($page, $key) {
    error_log('freePage()');
    $page = $this->cleanPageName($page);
    $free = $this->dbh->prepare("UPDATE {$this->pagesTable} SET lockkey='' ".
                                "WHERE lockkey=:key AND name=:page");
    $free->bindParam(':page', $page, PDO::PARAM_STR);
    $free->bindParam(':key', $key, PDO::PARAM_STR);
    if (!$free->execute()) {
      $free = null;
      error_log('freePage(): error in sql statement.');
      return false;
    }

    if ($free->rowCount() !== 1) {
      error_log('freePage(): affected rows: '.$free->rowCount());
      $free = null;
      return false;
    } else {
      $free = null;
      return true;
    }
  }
  
  function getDetailedChangeLog($count = 10) {
    $this->autoFree();
    $before_id = "IFNULL((SELECT C.id FROM {$this->revsTable} AS C ".
             "WHERE C.id < A.id AND C.page_id=A.page_id ORDER BY C.id DESC LIMIT 1), 0)";
    $before = "IFNULL((SELECT content FROM {$this->revsTable} AS C ".
             "WHERE C.id < A.id AND C.page_id=A.page_id ORDER BY C.id DESC LIMIT 1), '')";
    $q = "SELECT content AS `content_after`, ".
         "$before AS `content_before`, ".
         "$before_id AS `before_id`, ".
         "remote_ip, remote_agent, ".
         "timestamp_change AS timestamp, ".
         "name, A.id AS revision_id, B.id as page_id ".
         "FROM {$this->revsTable} AS A,{$this->pagesTable} AS B ".
         "WHERE A.page_id=B.id AND A.timestamp_change IS NOT NULL ".
         "ORDER BY A.timestamp_change DESC ".
         "LIMIT :count";
    $s = $this->dbh->prepare($q);
    $s->bindParam(':count', $count, PDO::PARAM_INT);
    if (!$s->execute()) return false;
    $list = $s->fetchAll();
    $s = null;
    return $list;
  }

  function getRecentChanges($count = 10) {
    $changes = "";
    $timestamp = time();
    $q = "SELECT name,MAX(timestamp_change) AS ts FROM {$this->pagesTable},{$this->revsTable} ".
         "WHERE {$this->pagesTable}.id=page_id AND timestamp_change IS NOT NULL ".
         "GROUP BY page_id ORDER BY ts DESC";
    if (is_numeric($count)) {
      $q .= " LIMIT $count";
    }
    $list = array();
    foreach ($this->dbh->query($q) as $row) {
      $seconds = $timestamp - $row['ts'];
      $minutes = floor($seconds / 60); $seconds %= 60;
      $hours = floor($minutes / 60); $minutes %= 60;
      $days = floor($hours / 24); $hours %= 24;
      $changes = "";
      if ($days > 0)
        $changes .= "${days}d ";
      if ($days > 0 || $hours > 0)
        $changes .= str_pad($hours, 2, '0', STR_PAD_LEFT).'h';
      if ($days > 0 || $hours > 0 || $minutes > 0)
        $changes .= str_pad($minutes, 2, '0', STR_PAD_LEFT).'m';
      $changes .= str_pad($seconds, 2, '0', STR_PAD_LEFT).'s';
      $list[] = array('name'       => $row['name'],
                      'howlongago' => $changes);
    }
    return $list;
  }
  
  function getRecentVisits($count = 10) {
    $changes = "";
    $timestamp = time();
    $q = "SELECT name,timestamp_visit FROM {$this->pagesTable} ".
         "ORDER BY timestamp_visit DESC";
    if (is_numeric($count)) {
      $q .= " LIMIT $count";
    }
    $list = array();
    foreach ($this->dbh->query($q) as $r) {
      $seconds = $timestamp - $r['timestamp_visit'];
      $minutes = floor($seconds / 60); $seconds %= 60;
      $hours = floor($minutes / 60); $minutes %= 60;
      $days = floor($hours / 24); $hours %= 24;
      $changes = "";
      if ($days > 0)
        $changes .= "${days}d ";
      if ($days > 0 || $hours > 0)
        $changes .= str_pad($hours, 2, '0', STR_PAD_LEFT).'h';
      if ($days > 0 || $hours > 0 || $minutes > 0)
        $changes .= str_pad($minutes, 2, '0', STR_PAD_LEFT).'m';
      $changes .= str_pad($seconds, 2, '0', STR_PAD_LEFT).'s';
      $list[] = array('name'       => $r['name'],
                      'howlongago' => $changes);
    }
    return $list;
  }

  function getPageList() {
    $pages = array();
    foreach ($this->dbh->query("SELECT name FROM {$this->pagesTable} ORDER BY name ASC") as $r) {
      $pages[] = $r[0];
    }
    return $pages;
  }
  
  function getPagesContaining($what) {
    $what = '%'.$this->cleanPageName($what).'%';
    $s = $this->dbh->prepare("SELECT name, content, timestamp_change, timestamp_visit ".
                             "FROM {$this->pagesTable} AS P, ".
                             "{$this->revsTable} AS R ".
                             "WHERE R.id=P.revision_id AND content LIKE :what ".
                             "ORDER BY name ASC");
    $s->bindParam(':what', $what, PDO::PARAM_STR);
    if ($s->execute() === false) {
      $s = null;
      $err = $this->dbh->errorInfo();
      error_log('getPagesContaining(): '.$err[2]);
      return false;
    }
    $pages = $s->fetchAll();
    $s = null;
    return $pages;
  }
  
  function getPageNamesContaining($what) {
    $what = '%'.$this->cleanPageName($what).'%';
    $s = $this->dbh->prepare("SELECT name FROM {$this->pagesTable} ".
                             "WHERE name LIKE :what ".
                             "ORDER BY name ASC");
    $s->bindParam(':what', $what, PDO::PARAM_STR);
    if ($s->execute() === false) {
      $s = null;
      $err = $this->dbh->errorInfo();
      error_log('getPageNamesContaining(): '.$err[2]);
      return false;
    }
    $pages = array();
    while ($r = $s->fetch()) {
      $pages[] = $r['name'];
    }
    $s = null;
    return $pages;
  }
  
  function visitPage($page) {
    $page = $this->cleanPageName($page);
    $visit = time();
    $query = $this->dbh->prepare("UPDATE {$this->pagesTable} SET timestamp_visit=:visit WHERE name=:page");
    $query->bindParam(':visit', $visit, PDO::PARAM_INT);
    $query->bindParam(':page', $page, PDO::PARAM_STR);
    $r = $query->execute();
    $query = null;
    return $r;
  }
  
  function getRevision($rev) {
    $this->autoFree();
    $s = $this->dbh->prepare("SELECT name, content, timestamp_change ".
                             "FROM {$this->pagesTable} AS p, {$this->revsTable} AS r ".
                             "WHERE r.id=:rev AND p.id=r.page_id");
    $s->bindParam(':rev', $rev, PDO::PARAM_INT);
    if ($s->execute() === false) {
      $s = null;
      $err = $this->dbh->errorInfo();
      error_log('getRevision(): '.$err[2]);
      return false;
    }

    if (($r = $s->fetch()) === false) {
      return array('name'             => '',
                   'content'          => '',
                   'timestamp_change' => 0);
    }

    $s = null;
    return $r;
  }
   
  function getPage($page) {
    $this->autoFree();
    $page = $this->cleanPageName($page);
    $p = "{$this->pagesTable}";
    $r = "{$this->revsTable}";
    $q = "SELECT revision_id,name,content,timestamp_change,timestamp_visit,timestamp_lock,remote_ip,remote_agent,lockkey FROM $p,$r ".
         "WHERE name=:pagename AND $p.revision_id=$r.id";
    $s = $this->dbh->prepare($q);
    $s->bindParam(':pagename', $page, PDO::PARAM_STR);
    if (!$s->execute()) {
      error_log($q);
      trigger_error("could not get content of page $page");
      return false;
    }
    if (!($r = $s->fetch())) {
      // page does not exist
      $s = null;
      return array('name'             => $page,
                   'content'          => '',
                   'timestamp_change' => 1);
    }
    $s = null;
    return $r;
  }
  
  function getTimestamp($page) {
    $this->autoFree();
    $page = $this->cleanPageName($page);
    $p = "{$this->pagesTable}";
    $r = "{$this->revsTable}";
    $select = $this->dbh->prepare("SELECT timestamp_change FROM $p,$r ".
                                  "WHERE name=:page AND $p.revision_id=$r.id");
    $select->bindParam(':page', $page, PDO::PARAM_STR);

    if (!$select->execute()) {
      trigger_error("could not get timestamp of page $page");
      $select = null;
      return false;
    }
    if (!($r = $select->fetch())) {
      // page does not exist
      $select = null;
      return false;
    }
    $select = null;
    return is_numeric($r['timestamp_change']) ? $r['timestamp_change'] : 0;
  }

  function renewLock($page, $key) {
    $now = time();
    $tslock = $now - 180;
    $page = $this->cleanPageName($page);
    $stmt = $this->dbh->prepare("UPDATE {$this->pagesTable} SET timestamp_lock=:now ".
                               "WHERE name=:page AND lockkey=:key AND timestamp_lock >= :tslock");
    $stmt->bindParam(':now', $now, PDO::PARAM_INT);
    $stmt->bindParam(':page', $page, PDO::PARAM_STR);
    $stmt->bindParam(':key', $key, PDO::PARAM_STR);
    $stmt->bindParam(':tslock', $tslock, PDO::PARAM_INT);
    $r = $stmt->execute();
    $stmt = null;
    if (!$r) {
      $err = $this->dbh->errorInfo();
      die($err[2]);
    }
    return true;
  }

  function updatePage($page, $key, $content) {
    error_log("updatePage($page, $key)");
    $page = $this->cleanPageName($page);
    $timestamp = time();
    $ip = sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
    $agent = $_SERVER['HTTP_USER_AGENT'];

    $this->dbh->beginTransaction();

    // first, renew the lock
    if (!$this->renewLock($page, $key)) {
      error_log('updatePage(): lost lock --> FALSE');

      $this->dbh->rollback();
      return false;
    }

    // convert windows & (old) mac newlines to unix ones:
    $content = str_replace(array("\r\n", "\r"), "\n", $content);
    // remove superficious spaces/lines
    $content = trim($content);

    $oldpage = $this->getPage($page);
    if ($content == $oldpage['content']) {
      error_log('updatePage(): no changes --> TRUE');
      $this->dbh->rollback();
      return true;
    }
    
    $select = $this->dbh->prepare("SELECT id, has_changes, revision_id ".
                                  "FROM {$this->pagesTable} WHERE name=:page AND lockkey=:key");
    $select->bindParam(':page', $page, PDO::PARAM_STR);
    $select->bindParam(':key', $key, PDO::PARAM_STR);
    if (!$select->execute()) {
      error_log('updatePage(): Error processing request --> FALSE');
      $select = null;
      $this->dbh->rollback();
      return false;
    }
    
    $res = $select->fetchAll();
    $select = null;
    error_log(count($res).' results.');

    if (count($res) != 1) {
      error_log('updatePage(): page not found or don\'t have lock --> FALSE');
      $this->dbh->rollback();
      return false;
    }
    error_log('updatePage(): Page found.');
    $row = $res[0];
    $id = $row['id'];

    if ($row['has_changes'] == 'N') {
      error_log('updatePage(): revision has no changes.');
      $insert = $this->dbh->prepare("INSERT INTO {$this->revsTable} (page_id, timestamp_change, content, remote_ip, remote_agent) ".
                                    "VALUES(:pageid, :timestamp, :content, :remoteip, :remoteagent)");
      $insert->bindParam(':pageid', $id, PDO::PARAM_INT);
      $insert->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
      $insert->bindParam(':content', $content, PDO::PARAM_STR);
      $insert->bindParam(':remoteip', $ip, PDO::PARAM_INT);
      $insert->bindParam(':remoteagent', $agent, PDO::PARAM_STR);
      if (!$insert->execute()) {
        error_log('updatePage(): unable to insert revision --> FALSE');
        $insert = null;
        $this->dbh->rollback();
        return false;
      }
      $insert = null;
      $revid = $this->dbh->lastInsertId();
      error_log("updatePage(): created new revision #$revid");
      $update = $this->dbh->prepare("UPDATE {$this->pagesTable} SET revision_id=:revid, has_changes='Y' WHERE id=:pageid");
      $update->bindParam(':revid', $revid, PDO::PARAM_INT);
      $update->bindParam(':pageid', $id, PDO::PARAM_INT);

      if (!$update->execute()) {
        error_log('updatePage(): unable to set pointer to newly created revision --> FALSE');
        $update = null;
        $this->dbh->rollback();
        return false;
      }

      $update = null;
    } else {
      $revid = $row['revision_id'];
      $update = $this->dbh->prepare("UPDATE {$this->revsTable} ".
                               "SET timestamp_change=:timestamp, content=:content, remote_ip=:remoteip, remote_agent=:remoteagent ".
                               "WHERE id=:revid");
      $update->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
      $update->bindParam(':content', $content, PDO::PARAM_STR);
      $update->bindParam(':remoteip', $ip, PDO::PARAM_INT);
      $update->bindParam(':remoteagent', $agent, PDO::PARAM_STR);
      $update->bindParam(':revid', $revid, PDO::PARAM_INT);
      if (!$update->execute()) {
        error_log('updatePage(): unable to update revision --> FALSE');
        $update = null;
        $this->dbh->rollback();
        return false;
      }
      $update = null;
    }

    error_log('updatePage(): page revision updated/created. --> TRUE');
    $this->dbh->commit();
    return true;
  }

  function deletePage($page) {
    $page = $this->cleanPageName($page);
    // TODO: implement
  }

  function expungePage($page) {
    $page = $this->cleanPageName($page);
    $delRevs = $this->dbh->prepare("DELETE FROM {$this->revsTable} WHERE page_id=(SELECT id FROM {$this->pagesTable} WHERE name=:page)");
    $delPage = $this->dbh->prepare("DELETE FROM {$this->pagesTable} WHERE name=:page");
    $delRevs->bindParam(':page', $page, PDO::PARAM_STR);
    $delPage->bindParam(':page', $page, PDO::PARAM_STR);
    $this->dbh->beginTransaction();
    if ($delRevs->execute() === false) {
      $delRevs = null;
      $delPage = null;
      $this->dbh->rollback();
      return false;
    }
    $delRevs = null;
    if ($delPage->execute() == false) {
      $delPage = null;
      $this->dbh->rollback();
      return false;
    }
    $delPage = null;
    $this->dbh->commit();
    return true;
  }

  function closeConnection() {
    $this->dbh = null;
  }
}
?>
