<?php

add_to_known_languages( "sv", "Svenska" );

class sv_Language extends Language
{
  function sv_Language()
    {
      $this->translated_strings =
        array( /* From std_functions.php */
               "Status" =>
               "Status",

               "Messages" =>
               "Meddelanden",

               "Users" =>
               "Anv�ndare",

               "Forums" =>
               "Forum",

               "Invite" =>
               "Bjud in",

               "Docs" =>
               "Dokumentation",

               "Logged in as" =>
               "Inloggad som",

               "Not logged in" =>
               "Ej inloggad",

               "Logout" =>
               "Logga ut",

               /* From index.php */
               "Home" =>
               "Hemma",

               "Please login." =>
               "Logga in, tack.",

               "To look around, use %s." =>
               "Anv�nd %s f�r att se dig omkring.",

               "Register new account" =>
               "Registrera ett nytt konto",

               "Log in" =>
               "Logga in",

               "Forgot password?" =>
               "Gl�mt l�senordet?",

               /* From edit_profile.php */

               "Edit profile" =>
               "�ndra din profil",

               "Personal settings" =>
               "Personliga inst�llningar",

               "Board graphics" =>
               "Br�dgrafik",

               /* From error.php */

               "Sorry, you have to be logged in to do that.\n" .
               "<p>\n" .
               "The reasons for this problem could be any of the following:\n" .
               "<ul>\n" .
               "<li> You haven't got an <a href=\"%1\$s/register.php\">account</a>, or haven't <a href=\"%1\$s/index.php\">logged in</a> yet.\n" .
               "<li> Your cookies have expired. This happens once a week.\n" .
               "<li> You haven't enabled cookies in your browser.\n" .
               "</ul>\n" =>

               "Tyv�rr, men du m�ste vara inloggad f�r att g�ra detta.\n" .
               "<p>\n" .
               "Anledningen till detta problem kan vara ett av f�ljande:\n" .
               "<ul>\n" .
               "<li> Du har inget <a href=\"%1\$s/register.php\">konto</a>, eller s� har du inte <a href=\"%1\$s/index.php\">loggat in</a> �n.\n" .
               "<li> Dina kakor har blivit ogiltiga. Detta h�nder en g�ng i veckan.\n" .
               "<li> Du har inte slagit p� kakor i din webbl�sare.\n" .
               "</ul>\n",

               /* Other things */
               "Userid" =>
               "Anv�ndaridentitet",

               "Password" =>
               "L�senord",

               "Full name" =>
               "Hela namnet",

               "Email" =>
               "Epost",

               "Yes" =>
               "Ja",

               "No" =>
               "Nej"

               );
    }
};

?>
