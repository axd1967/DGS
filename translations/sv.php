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
               "Användare",

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
               "Använd %s för att se dig omkring.",

               "Register new account" =>
               "Registrera ett nytt konto",

               "Log in" =>
               "Logga in",

               "Forgot password?" =>
               "Glömt lösenordet?",

               /* From edit_profile.php */

               "Edit profile" =>
               "Ändra din profil",

               "Personal settings" =>
               "Personliga inställningar",

               "Board graphics" =>
               "Brädgrafik",

               /* From error.php */

               "Sorry, you have to be logged in to do that.\n" .
               "<p>\n" .
               "The reasons for this problem could be any of the following:\n" .
               "<ul>\n" .
               "<li> You haven't got an <a href=\"%1\$s/register.php\">account</a>, or haven't <a href=\"%1\$s/index.php\">logged in</a> yet.\n" .
               "<li> Your cookies have expired. This happens once a week.\n" .
               "<li> You haven't enabled cookies in your browser.\n" .
               "</ul>\n" =>

               "Tyvärr, men du måste vara inloggad för att göra detta.\n" .
               "<p>\n" .
               "Anledningen till detta problem kan vara ett av följande:\n" .
               "<ul>\n" .
               "<li> Du har inget <a href=\"%1\$s/register.php\">konto</a>, eller så har du inte <a href=\"%1\$s/index.php\">loggat in</a> än.\n" .
               "<li> Dina kakor har blivit ogiltiga. Detta händer en gång i veckan.\n" .
               "<li> Du har inte slagit på kakor i din webbläsare.\n" .
               "</ul>\n",

               /* Other things */
               "Userid" =>
               "Användaridentitet",

               "Password" =>
               "Lösenord",

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
