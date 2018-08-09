-- phpMyAdmin SQL Dump
-- version 4.8.2
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Gegenereerd op: 09 aug 2018 om 13:03
-- Serverversie: 10.3.8-MariaDB-1:10.3.8+maria~jessie
-- PHP-versie: 7.2.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `groupoffice`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_address`
--

CREATE TABLE `addressbook_address` (
  `id` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `zipCode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `state` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `country` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_addressbook`
--

CREATE TABLE `addressbook_addressbook` (
  `id` int(11) NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aclId` int(11) NOT NULL,
  `createdBy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `addressbook_addressbook`
--

INSERT INTO `addressbook_addressbook` (`id`, `name`, `aclId`, `createdBy`) VALUES
(1, 'Prospects', 15, 1),
(2, 'Suppliers', 16, 1),
(3, 'Customers', 17, 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_contact`
--

CREATE TABLE `addressbook_contact` (
  `id` int(11) NOT NULL,
  `addressBookId` int(11) NOT NULL,
  `createdBy` int(11) NOT NULL,
  `createdAt` datetime NOT NULL,
  `modifiedAt` datetime NOT NULL,
  `prefixes` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Prefixes like ''Sir''',
  `firstName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `middleName` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lastName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `suffixes` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Suffixes like ''Msc.''',
  `gender` enum('M','F') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M for Male, F for Female or null for unknown',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isOrganization` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'name field for companies and contacts. It should be the display name of first, middle and last name',
  `IBAN` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `registrationNumber` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Company trade registration number',
  `vatNo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debtorNumber` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photoBlobId` binary(40) DEFAULT NULL,
  `language` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `addressbook_contact`
--

INSERT INTO `addressbook_contact` (`id`, `addressBookId`, `createdBy`, `createdAt`, `modifiedAt`, `prefixes`, `firstName`, `middleName`, `lastName`, `suffixes`, `gender`, `notes`, `isOrganization`, `name`, `IBAN`, `registrationNumber`, `vatNo`, `debtorNumber`, `photoBlobId`, `language`) VALUES
(3, 1, 1, '2018-08-06 15:05:22', '2018-08-07 13:29:46', '', 'Merijn', '', 'Schering', '', NULL, NULL, 0, 'Merijn Schering', '', '', NULL, NULL, 0x65626638323630613061393235616430623737336536373566323636333966303464356332643662, NULL),
(4, 1, 1, '2018-08-06 15:15:45', '2018-08-07 13:38:19', '', 'Jan', 'van der', 'Steen', '', NULL, NULL, 0, 'Jan van der Steen', '', '', NULL, NULL, 0x31396563353732613233323632653864313939653937666665393737333136386234626665323733, NULL),
(5, 1, 1, '2018-08-07 13:39:44', '2018-08-07 13:48:30', '', '', '', '', '', NULL, NULL, 1, 'Intermesh BV', '', '', NULL, NULL, NULL, NULL),
(6, 1, 1, '2018-08-07 13:47:23', '2018-08-07 13:47:23', '', 'Michael', 'de', 'Hart', '', NULL, NULL, 0, 'Michael de Hart', '', '', NULL, NULL, NULL, NULL),
(7, 1, 1, '2018-08-07 14:20:11', '2018-08-07 14:20:11', '', '', '', '', '', NULL, NULL, 1, 'Group Office Inc.', '', '', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_contact_custom_fields`
--

CREATE TABLE `addressbook_contact_custom_fields` (
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_contact_organization`
--

CREATE TABLE `addressbook_contact_organization` (
  `contactId` int(11) NOT NULL,
  `organizationContactId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `addressbook_contact_organization`
--

INSERT INTO `addressbook_contact_organization` (`contactId`, `organizationContactId`) VALUES
(3, 5),
(3, 7);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_date`
--

CREATE TABLE `addressbook_date` (
  `id` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'birthday',
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_email_address`
--

CREATE TABLE `addressbook_email_address` (
  `id` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `addressbook_email_address`
--

INSERT INTO `addressbook_email_address` (`id`, `contactId`, `type`, `email`) VALUES
(1, 3, 'home', 'mschering@intermesh.nl'),
(3, 4, 'test', 'piet@intermesh.nl'),
(4, 3, 'work', 'merijn@intermesh.nl'),
(5, 5, 'work', 'info@intermesh.nl');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_phone_number`
--

CREATE TABLE `addressbook_phone_number` (
  `id` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

--
-- Gegevens worden geëxporteerd voor tabel `addressbook_phone_number`
--

INSERT INTO `addressbook_phone_number` (`id`, `contactId`, `type`, `number`) VALUES
(1, 3, 'mobile', '0619864268'),
(2, 3, 'work', '0732046000'),
(3, 3, 'mobile', '23442343'),
(4, 5, 'work', '0732046000');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `addressbook_url`
--

CREATE TABLE `addressbook_url` (
  `id` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `addressbook_address`
--
ALTER TABLE `addressbook_address`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contactId` (`contactId`);

--
-- Indexen voor tabel `addressbook_addressbook`
--
ALTER TABLE `addressbook_addressbook`
  ADD PRIMARY KEY (`id`),
  ADD KEY `acid` (`aclId`),
  ADD KEY `createdBy` (`createdBy`);

--
-- Indexen voor tabel `addressbook_contact`
--
ALTER TABLE `addressbook_contact`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner` (`createdBy`),
  ADD KEY `photoBlobId` (`photoBlobId`),
  ADD KEY `addressBookId` (`addressBookId`);

--
-- Indexen voor tabel `addressbook_contact_custom_fields`
--
ALTER TABLE `addressbook_contact_custom_fields`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `addressbook_contact_organization`
--
ALTER TABLE `addressbook_contact_organization`
  ADD PRIMARY KEY (`contactId`,`organizationContactId`),
  ADD KEY `organizationContactId` (`organizationContactId`);

--
-- Indexen voor tabel `addressbook_date`
--
ALTER TABLE `addressbook_date`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contactId` (`contactId`);

--
-- Indexen voor tabel `addressbook_email_address`
--
ALTER TABLE `addressbook_email_address`
  ADD PRIMARY KEY (`id`,`contactId`),
  ADD KEY `contactId` (`contactId`);

--
-- Indexen voor tabel `addressbook_phone_number`
--
ALTER TABLE `addressbook_phone_number`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contactId` (`contactId`);

--
-- Indexen voor tabel `addressbook_url`
--
ALTER TABLE `addressbook_url`
  ADD PRIMARY KEY (`id`,`contactId`),
  ADD KEY `contactId` (`contactId`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `addressbook_address`
--
ALTER TABLE `addressbook_address`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `addressbook_addressbook`
--
ALTER TABLE `addressbook_addressbook`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT voor een tabel `addressbook_contact`
--
ALTER TABLE `addressbook_contact`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT voor een tabel `addressbook_date`
--
ALTER TABLE `addressbook_date`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `addressbook_email_address`
--
ALTER TABLE `addressbook_email_address`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT voor een tabel `addressbook_phone_number`
--
ALTER TABLE `addressbook_phone_number`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `addressbook_url`
--
ALTER TABLE `addressbook_url`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `addressbook_address`
--
ALTER TABLE `addressbook_address`
  ADD CONSTRAINT `addressbook_address_ibfk_1` FOREIGN KEY (`contactId`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `addressbook_addressbook`
--
ALTER TABLE `addressbook_addressbook`
  ADD CONSTRAINT `addressbook_addressbook_ibfk_1` FOREIGN KEY (`aclId`) REFERENCES `core_acl` (`id`);

--
-- Beperkingen voor tabel `addressbook_contact`
--
ALTER TABLE `addressbook_contact`
  ADD CONSTRAINT `addressbook_contact_ibfk_1` FOREIGN KEY (`addressBookId`) REFERENCES `addressbook_addressbook` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `addressbook_contact_ibfk_2` FOREIGN KEY (`photoBlobId`) REFERENCES `core_blob` (`id`);

--
-- Beperkingen voor tabel `addressbook_contact_custom_fields`
--
ALTER TABLE `addressbook_contact_custom_fields`
  ADD CONSTRAINT `addressbook_contact_custom_fields_ibfk_1` FOREIGN KEY (`id`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `addressbook_contact_organization`
--
ALTER TABLE `addressbook_contact_organization`
  ADD CONSTRAINT `addressbook_contact_organization_ibfk_1` FOREIGN KEY (`contactId`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `addressbook_contact_organization_ibfk_2` FOREIGN KEY (`organizationContactId`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `addressbook_date`
--
ALTER TABLE `addressbook_date`
  ADD CONSTRAINT `addressbook_date_ibfk_1` FOREIGN KEY (`contactId`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `addressbook_email_address`
--
ALTER TABLE `addressbook_email_address`
  ADD CONSTRAINT `addressbook_email_address_ibfk_1` FOREIGN KEY (`contactId`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `addressbook_phone_number`
--
ALTER TABLE `addressbook_phone_number`
  ADD CONSTRAINT `addressbook_phone_number_ibfk_1` FOREIGN KEY (`contactId`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `addressbook_url`
--
ALTER TABLE `addressbook_url`
  ADD CONSTRAINT `addressbook_url_ibfk_1` FOREIGN KEY (`contactId`) REFERENCES `addressbook_contact` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
