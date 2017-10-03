<?php

/* eerst maken we een database conectie en daar hebben we een naam en een wachtwoord voor nodig.
 * */
$user = 'root';
$wachtwoord = '';

/* als we een user en wachtwoord hebben maken we een try en catch connectie,
 * met zowel de oude als de nieuwe database.
 *
 * de try probeert de verbinden, en de catch geeft een error bericht als hij geen verbinding kan maken
 * */

try {
    $db1 = new PDO('mysql:host=localhost;dbname=old', $user, $wachtwoord);
    $db2 = new PDO('mysql:host=localhost;dbname=new', $user, $wachtwoord);
} catch (PDOException $e) {
    print "Error!" . $e->getMessage() . "</br>";
    die();
}

/* hieronder SELECTEER en FETCH ik alles van de tabel users.
 * */
$userQuery = $db1->prepare('SELECT * FROM users');
$userQuery->execute();
$users = $userQuery->fetchAll(PDO::FETCH_OBJ);

/* en dan maak ik van users een foreach om van een array naar string te converteren
 * */
foreach ($users as $user) {

    /* de geselecteren oude database tabel: users, word via een INSERT INTO de adres zoals de straat,
     * huisnr en postcode verwerkt en overgeplaatst naar de nieuwe database.
     * */
    $insAdr = $db2->prepare('INSERT INTO addresses(street, house_number, postcode) VALUES (:street, :house_number,:postcode)');
    $insAdr->bindParam(':street', $user->street);
    $insAdr->bindParam(':house_number', $user->house_number);
    $insAdr->bindParam(':postcode', $user->postcode);
    $insAdr->execute();
    $adressID = $db2->lastInsertId();

    /* de geselecteren oude database tabel: users, word hier via een INSERT INTO de profiel
     * voornaam en achternaam eerst gexplodeerd dit is omdat de voornaam en achternaam in 1 kolom zitten en omdat die in de nieuwe database gesplitst moeten worden,
     * na dat de voornaam en achternaam is gesplitst word de data verwerkt en overgeplaatst naar een eigen kolom in de nieuwe database.
     * */
    $insProf = $db2->prepare('INSERT INTO profiles (first_name, last_name) VALUES (:first_name, :last_name) ');
    $newnaam = explode(' ', $user->name);
    $insProf->bindParam(':first_name', $newnaam [0]);
    $insProf->bindParam(':last_name', $newnaam [1]);
    $insProf->execute();
    $profID = $db2->lastInsertId();

    /* om rollen te toevoegen aan de nieuwe database wil ik eigenlijk naar 1 van elke rol naar de nieuwe database overplaatsen,
     * voor dat ik deze overplaats moeten de rollen eerst geteld worden,
     * dat doen we door dit te selecteren met een count functie van de tabel: roles en dit gaan we vergelijken op naam,
     *
     * */
    $queryrole = $db2->prepare('SELECT count(*) FROM roles WHERE name=:name');
    $queryrole->bindParam(':name', $user->role);
    $queryrole->execute();
    $countqueryrole = $queryrole->fetchColumn();

    /*
     * De countqueryrole gaat alle kolommen pakken die hij heeft geteld op naam,
     * en door hier een: (IF countqueryrole == 1) van te maken gaat die zoeken of er al een vergeljkte rol in de nieuwe database staat,
     * staat de rol nou niet is de nieuwe database dan gaat de else in werking om de rollen op die zojuist vergeleken zijn toe te voegen,
     * en dan moet er maar 1 rol op naam verwerkt en toegevoegd naar de nieuwe database.
     * */

    if ($countqueryrole == 1) {
        $select = $db2->prepare('SELECT * FROM roles WHERE name=:name');
        $select->bindParam(':name', $user->role);
        $select->execute();
        $rolefetch = $select->fetch(PDO::FETCH_OBJ);
        $roleID = $rolefetch->id;
    } else {
        $insrole = $db2->prepare('INSERT INTO roles (name) VALUES (:name)');
        $insrole->bindParam(':name', $user->role);
        $insrole->execute();
        $roleID = $db2->lastInsertId();

    }

    /*
     * omdat alles van tabel: users nog steeds word uitgevoerd met de foreach kunnen we de email en wachtwoord direct uit de database halen,
     * maar dan blijft de foreign keys Address_id, Profile_id en Role_id over en die moeten natuurlijk ook meegenomen worden,
     * en omdat wij de INSERT INTO addresses, profiles en roles hebben gemaakt en aan het eide van die inserts een lastInsertId hebben mee gegeven,
     * kunnen we de variable $ID gebruiken om deze vervolgens te verbinden aan de naam met bindParam.
     * */

    $insUser = $db2->prepare('INSERT INTO users (email, password, Address_id, Profile_id, Role_id) VALUES (:email, :password, :Address_id, :Profile_id, :Role_id)');
    $insUser->bindParam(':email', $user->email);
    $insUser->bindParam(':password', $user->password);
    $insUser->bindParam(':Address_id', $adressID);
    $insUser->bindParam(':Profile_id', $profID);
    $insUser->bindParam(':Role_id', $roleID);
    $insUser->execute();
    $userID = $db2->lastInsertId();

    /* 
     * hieronder moet er eerst weer een selectie worden uitgevoerd, dit is omdat blog in een eigen tabel staat en niet meer in de users tabel,
     * nadat de blog is geselecteerd moet de Users_id worden geselecteerd op de ID in blog,
     * en omdat de selectie van blog ook nog in de users-foreach staat, kan de WHERE de User_id vergelijken met de user ID uit de oude database.
     * */

    $blogQuery = $db1->prepare('SELECT * FROM blog WHERE Users_id =:id');
    $blogQuery->bindParam(':id', $user->id);
    $blogQuery->execute();
    $blogs = $blogQuery->fetchAll(PDO::FETCH_OBJ);
    foreach ($blogs as $blog) {

    /* 
     * na de selectie van de blog word er ook een foreach van blog gemaakt om vervolgens een INSERT INTO te uitvoeren,
     * dan moet de title en content uit de tabel blog worden gehaald, 
     * maar de User_id word weer uit de variabele $userID gehaald uit de tabel van users.
     * */

    $insBlog = $db2->prepare('INSERT INTO blogs (title, content, User_id) VALUES (:title, :content, :User_id)');
    $insBlog->bindParam(':title', $blog->title);
    $insBlog->bindParam(':content', $blog->content);
    $insBlog->bindParam(':User_id', $userID);
    $insBlog->execute();
    $blogID = $db2->lastInsertId();


   /* 
    * hier word bijna hetzelfde uitgevoerd als bij de tabel blog,
    * aleen dan voor de tabel comment maar omdat er ook een Blog_id uit de tabel blog moet komen zit de selectie van comment ook in de foreach van blog en de foreach van users,
    * maar hier word dan de ID van blog met een WHARE statement uit de tabel van blog gehaald.
    * */

    $commQuery = $db1->prepare('SELECT * FROM comment WHERE Blog_id =:id');
    $commQuery->bindParam(':id', $blog->id);
    $commQuery->execute();
    $comments = $commQuery->fetchAll(PDO::FETCH_OBJ);
    foreach ($comments as $comment){

   /* 
    * en ook hier word weer een INSERT INTO uitgevoerd om de text in de nieuwe database tabel: comments te verwerken,
    * en word de Blog_id uit de variabel van $blogID uit de tabel blog gehaald en uitgevoerd,
    * en de User_id word dan weer uit de users gehaald met variabel $userID.
  * */    

    $insComment = $db2->prepare('INSERT INTO comments (text, Blog_id, User_id) VALUES (:text, :Blog_id, :User_id)');
    $insComment->bindParam(':text', $comment->text);
    $insComment->bindParam(':Blog_id', $blogID);
    $insComment->bindParam(':User_id', $userID);
    $insComment->execute();
    $commentID = $db2->lastInsertId();

   /*
    * hieronder word de foreach van blog en comment afgesloten want die kunnen we niet gebruiken met de file tabel,
    * omdat de file tabel ook vergeleken moet worden op de User_id van de tabel users.
  * */

        }
    }

   /*
    * hier word een SELECTIE gemaakt in de foreach van user en word de tabel van uploaded_by vergeleken op name van alle users in de tabel user,
    * daarom staat file wel in de foreach user en niet meer in de tabel comment of blog omdat de naam dan dubbel worden vergelijkt.
  * */

    $fileQuery = $db1->prepare('SELECT * FROM file WHERE uploaded_by=:name');
    $fileQuery->bindParam(':name', $user->name);
    $fileQuery->execute();
    $files = $fileQuery->fetchAll(PDO::FETCH_OBJ);
    foreach ($files as $file){

   /*
    * als alles word geselecteerd van de tabel file kunnen we de data van filename toevoegen naar de nieuwe database,
    * en halen we de gegevens van User_id uit de users tabel met variabel $userID.
  * */

    $insfile = $db2->prepare('INSERT INTO files (filename, User_id) VALUES (:filename, :User_id)');
    $insfile->bindParam(':filename', $file->filename);
    $insfile->bindParam(':User_id', $userID);
    $insfile->execute();
    $fileID = $db2->lastInsertId();

    }
}


?>




