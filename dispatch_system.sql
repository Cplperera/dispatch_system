-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2026 at 12:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dispatch_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `desks`
--

CREATE TABLE `desks` (
  `id` int(11) NOT NULL,
  `desk_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `desks`
--

INSERT INTO `desks` (`id`, `desk_name`, `description`, `status`, `is_deleted`, `created_at`, `updated_at`) VALUES
(3, 'Alfa Desk', 'Desk Alfa', 'Active', 0, '2026-02-06 22:05:17', '2026-10-04 20:55:47'),
(4, 'Bravo Desk', 'Desk Bravo', 'Active', 0, '2026-02-06 22:05:17', '2026-07-26 11:34:11'),
(5, 'Charlie Desk', 'Central Desk', 'Active', 0, '2026-02-06 22:05:17', '2026-08-09 22:43:06'),
(6, 'Delta', 'Desk Delta', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(7, 'Echo', 'Desk Echo', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(8, 'Foxtrot Desk', 'Desk Foxtrot', 'Active', 0, '2026-02-06 22:05:17', '2026-07-26 11:12:16'),
(9, 'Golf Desk', 'State Affairs Desk', 'Active', 0, '2026-02-06 22:05:17', '2026-07-26 11:08:01'),
(10, 'Hotel Desk', 'Desk Hotel', 'Active', 0, '2026-02-06 22:05:17', '2026-08-11 18:02:06'),
(11, 'India Desk', 'Social Desk', 'Active', 0, '2026-02-06 22:05:17', '2026-07-26 11:16:57'),
(12, 'Juliet', 'Desk Juliet', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(13, 'Kilo', 'Desk Kilo', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(14, 'Lima', 'Desk Lima', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(15, 'Mike', 'Desk Mike', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(16, 'November', 'Desk November', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(17, 'Oscar Desk', 'Organized Crime Desk', 'Active', 0, '2026-02-06 22:05:17', '2026-08-09 22:43:37'),
(18, 'Papa', 'Desk Papa', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(19, 'Quebec', 'Desk Quebec', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(20, 'Romeo', 'Desk Romeo', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(21, 'Sierra', 'Desk Sierra', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(22, 'Tango', 'Desk Tango', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(23, 'Uniform', 'Desk Uniform', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(24, 'Victor', 'Desk Victor', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(25, 'Whiskey', 'Desk Whiskey', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(26, 'X-ray', 'Desk X-ray', 'Active', 0, '2026-02-06 22:05:17', '2026-02-06 22:05:17'),
(27, 'Yankee', 'Desk Yankee', 'Active', 0, '2026-02-06 22:05:17', '2026-10-04 20:56:20'),
(28, 'Zulu', 'Desk Zulu', 'Active', 0, '2026-02-06 22:05:17', '2026-10-04 20:55:31');

-- --------------------------------------------------------

--
-- Table structure for table `external_branches`
--

CREATE TABLE `external_branches` (
  `id` int(11) NOT NULL,
  `branch_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `external_branches`
--

INSERT INTO `external_branches` (`id`, `branch_name`, `address`, `status`, `is_deleted`, `created_at`, `updated_at`) VALUES
(5, 'NID', 'Battramulla - Colombo', 'Active', 0, '2026-03-22 22:40:40', '2026-03-22 22:41:55'),
(6, 'SIS', 'Colmbo', 'Active', 0, '2026-07-10 17:22:29', '2026-07-10 17:22:29'),
(7, 'TID', 'Colombo', 'Active', 0, '2026-07-11 17:26:24', '2026-07-11 17:26:24'),
(8, 'CCD', 'Mirihana, Colombo', 'Active', 0, '2026-08-30 18:40:32', '2026-08-30 18:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `file_issuances`
--

CREATE TABLE `file_issuances` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `issued_to` int(11) NOT NULL,
  `issued_by` int(11) NOT NULL,
  `issue_date` datetime DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('Issued','Returned') DEFAULT 'Issued',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `file_issuances`
--

INSERT INTO `file_issuances` (`id`, `file_id`, `issued_to`, `issued_by`, `issue_date`, `due_date`, `return_date`, `status`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 4, '2026-09-19 13:49:47', NULL, '2026-09-19 13:50:17', 'Returned', ' | Returned: ', '2026-09-19 08:19:47', '2026-09-19 08:20:17'),
(2, 3, 8, 4, '2026-09-19 13:50:58', NULL, '2026-09-19 13:59:01', 'Returned', ' | Returned: ', '2026-09-19 08:20:58', '2026-09-19 08:29:01'),
(3, 1, 9, 4, '2026-09-19 14:03:13', '2026-09-20', '2026-09-19 14:10:16', 'Returned', 'for the file | Returned: ', '2026-09-19 08:33:13', '2026-09-19 08:40:16'),
(4, 3, 9, 4, '2026-09-19 14:03:13', '2026-09-20', NULL, 'Issued', 'for the file', '2026-09-19 08:33:13', '2026-09-19 08:33:13'),
(5, 2, 9, 4, '2026-09-19 14:03:13', '2026-09-20', '2026-09-19 14:03:40', 'Returned', 'for the file | Returned: ', '2026-09-19 08:33:13', '2026-09-19 08:33:40'),
(6, 6, 4, 4, '2026-09-24 11:04:16', NULL, '2026-10-04 14:17:53', 'Returned', ' | Returned: ', '2026-09-24 05:34:16', '2026-10-04 08:47:53'),
(7, 1, 8, 4, '2026-10-04 14:14:30', '2026-10-05', NULL, 'Issued', '', '2026-10-04 08:44:30', '2026-10-04 08:44:30'),
(8, 11, 8, 4, '2026-10-04 14:14:30', '2026-10-05', NULL, 'Issued', '', '2026-10-04 08:44:30', '2026-10-04 08:44:30');

-- --------------------------------------------------------

--
-- Table structure for table `informers`
--

CREATE TABLE `informers` (
  `id` int(11) NOT NULL,
  `informer_code` varchar(50) NOT NULL,
  `informer_name` varchar(150) NOT NULL,
  `category` enum('Informer','Casual Contact','Agent') NOT NULL,
  `classification` enum('A','B','C','D','E','F') NOT NULL,
  `handler_details` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regiment_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `informers`
--

INSERT INTO `informers` (`id`, `informer_code`, `informer_name`, `category`, `classification`, `handler_details`, `contact_number`, `status`, `is_deleted`, `created_at`, `updated_at`, `regiment_id`) VALUES
(9, '1-123A', 'Akila', 'Agent', 'A', 'Sgt Wimal WER', '0771234567', 'Active', 0, '2026-07-25 14:08:14', '2026-07-25 14:08:14', 1),
(10, '1-175CC', 'Amal', 'Informer', 'A', 'Cpl Kamal', '0112456789', 'Active', 0, '2026-07-29 18:44:41', '2026-07-29 18:44:41', 2);

-- --------------------------------------------------------

--
-- Table structure for table `internal_letters`
--

CREATE TABLE `internal_letters` (
  `id` int(11) NOT NULL,
  `cdr_number` varchar(30) NOT NULL,
  `letter_reference` varchar(100) DEFAULT NULL,
  `letter_title` varchar(255) NOT NULL,
  `letter_date` date NOT NULL,
  `regiment_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `letter_classification` int(11) DEFAULT NULL,
  `security_classification` enum('Public','Restricted','Confidential','Secret','Top Secret') NOT NULL DEFAULT 'Confidential',
  `priority_status` enum('Normal','Urgent','Immediately') NOT NULL DEFAULT 'Normal',
  `about_letter` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `Informer_id` int(11) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `registered_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `status` enum('Not Forwarded','Forwarded','pending','completed') DEFAULT 'Not Forwarded',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_under_final_disposal` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internal_letters`
--

INSERT INTO `internal_letters` (`id`, `cdr_number`, `letter_reference`, `letter_title`, `letter_date`, `regiment_id`, `category_id`, `letter_classification`, `security_classification`, `priority_status`, `about_letter`, `comments`, `Informer_id`, `attachment`, `registered_by`, `updated_by`, `status`, `is_deleted`, `created_at`, `updated_at`, `is_under_final_disposal`) VALUES
(63, 'REG/2026/01', 'DMI/INT/200/1/CD(01-01)', 'Sample Letter 1', '2026-07-01', 1, 2, NULL, 'Confidential', 'Normal', 'What is Lorem Ipsum?\r\nLorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum.', 'Why do we use it?\r\nIt is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using \'Content here, content here\', making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for \'lorem ipsum\' will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).', NULL, '1784394395_1.pdf', 4, NULL, 'Forwarded', 0, '2026-07-18 22:36:35', '2026-07-19 03:04:20', 0),
(64, 'REG/2026/02', 'DMI/INT/200/1/CD(01-02)', 'Sample Letter 2', '2026-06-28', 2, NULL, NULL, 'Secret', 'Urgent', 'Where does it come from?\r\nContrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in sections', 'Where can I get some?\r\nThere are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don\'t look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn\'t anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc.', NULL, '1784395848_a.pdf', 4, 4, 'Forwarded', 0, '2026-07-18 23:00:48', '2026-08-09 11:50:05', 0),
(65, 'REG/2026/03', 'DMI/INT/200/1/CD(01-03)', 'The standard Lorem Ipsum passage, used since 1966', '2026-06-04', 3, 2, NULL, 'Confidential', 'Immediately', '\"Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.\"', 'ection 1.10.32 of \"de Finibus Bonorum et Malorum\", written by Cicero in 45 BC\r\n\"Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?\"', NULL, '1784397461_a.pdf', 4, NULL, 'Forwarded', 0, '2026-07-18 23:27:41', '2026-07-19 02:40:25', 0),
(66, 'REG/2026/04', 'DMI/INT/200/1/CD(01-04)', '1914 translation by H. Rackham', '2026-07-19', 1, 2, NULL, 'Restricted', 'Normal', '\"On the other hand, we denounce with righteous indignation and dislike men who are so beguiled and demoralized by the charms of pleasure of the moment, so blinded by desire, that they cannot foresee the pain and trouble that are bound to ensue; and equal blame belongs to those who fail in their duty through weakness of will, which is the same as saying through shrinking from toil and pain. These cases are perfectly simple and easy to distinguish. In a free hour, when our power of choice is untrammelled and when nothing prevents our being able to do what we like best, every pleasure is to be welcomed and every pain avoided. But in certain circumstances and owing to the claims of duty or the obligations of business it will frequently occur that pleasures have to be repudiated and annoyances accepted. The wise man therefore always holds in these matters to this principle of selection: he rejects pleasures to secure other greater pleasures, or else he endures pains to avoid worse pains.\"', '\"On the other hand, we denounce with righteous indignation and dislike men who are so beguiled and demoralized by the charms of pleasure of the moment, so blinded by desire, that they cannot foresee the pain and trouble that are bound to ensue; and equal blame belongs to those who fail in their duty through weakness of will, which is the same as saying through shrinking from toil and pain. These cases are perfectly simple and easy to distinguish. In a free hour, when our power of choice is untrammelled and when nothing prevents our being able to do what we like best, every pleasure is to be welcomed and every pain avoided. But in certain circumstances and owing to the claims of duty or the obligations of business it will frequently occur that pleasures have to be repudiated and annoyances accepted. The wise man therefore always holds in these matters to this principle of selection: he rejects pleasures to secure other greater pleasures, or else he endures pains to avoid worse pains.\"', NULL, '1784408112_a.pdf', 4, NULL, 'pending', 0, '2026-07-19 02:25:12', '2026-09-14 14:04:20', 1),
(67, 'REG/2026/05', 'DMI/INT/200/1/CD(01-05)', 'Sample Letter 5', '2026-07-08', 5, 2, NULL, 'Secret', 'Normal', '\"At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.\"', '\"At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.\"', NULL, '1784408986_a.pdf', 4, NULL, 'pending', 0, '2026-07-19 02:39:46', '2026-09-14 14:04:23', 1),
(68, 'REG/2026/06', 'DMI/INT/200/1/CD(01-06)', 'olutpat consequat felis vitae venenatis.', '2026-07-07', 6, 2, NULL, 'Confidential', 'Immediately', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam euismod elementum tortor aliquam semper. Aenean volutpat ullamcorper lacus varius varius. Morbi purus lacus, vestibulum nec fringilla eu, imperdiet eget dolor. Duis mauris sem, mollis vel risus sed, ornare sodales velit. Interdum et malesuada fames ac ante ipsum primis in faucibus. Nunc mattis hendrerit dolor sed hendrerit. Vestibulum a varius libero. Donec eu commodo lorem, quis egestas turpis. Nullam rutrum dui elementum velit scelerisque porttitor. Curabitur sed dolor sit amet nunc auctor sodales.', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam euismod elementum tortor aliquam semper. Aenean volutpat ullamcorper lacus varius varius. Morbi purus lacus, vestibulum nec fringilla eu, imperdiet eget dolor. Duis mauris sem, mollis vel risus sed, ornare sodales velit. Interdum et malesuada fames ac ante ipsum primis in faucibus. Nunc mattis hendrerit dolor sed hendrerit. Vestibulum a varius libero. Donec eu commodo lorem, quis egestas turpis. Nullam rutrum dui elementum velit scelerisque porttitor. Curabitur sed dolor sit amet nunc auctor sodales.', NULL, '1784409359_a.pdf', 4, NULL, 'completed', 0, '2026-07-19 02:45:59', '2026-09-14 15:36:44', 1),
(69, 'REG/2026/07', 'DMI/INT/200/1/CD(01-07)', 's libero sed ante. Sed venenatis et lectus i', '2026-07-01', 7, 3, NULL, 'Secret', 'Urgent', 'Etiam pretium est non odio luctus, a ullamcorper metus maximus. Donec elementum, nisl in dapibus rhoncus, tellus neque condimentum augue, in interdum felis ex eu felis. Donec viverra, arcu id iaculis auctor, tortor ipsum congue dui, nec venenatis felis libero sed ante. Sed venenatis et lectus id tincidunt. Aenean in risus diam. Praesent luctus aliquam felis, id facilisis lacus bibendum eget. Integer viverra ipsum erat, sed rhoncus tortor tincidunt vitae. Donec sagittis hendrerit mauris at blandit. Donec risus metus, aliquam ut ornare vel, blandit at lorem.', 'Etiam pretium est non odio luctus, a ullamcorper metus maximus. Donec elementum, nisl in dapibus rhoncus, tellus neque condimentum augue, in interdum felis ex eu felis. Donec viverra, arcu id iaculis auctor, tortor ipsum congue dui, nec venenatis felid tincidunt. Aenean in risus diam. Praesent luctus aliquam felis, id facilisis lacus bibendum eget. Integer viverra ipsum erat, sed rhoncus tortor tincidunt vitae. Donec sagittis hendrerit mauris at blandit. Donec risus metus, aliquam ut ornare vel, blandit at lorem.', NULL, '1784409993_a.pdf', 4, NULL, 'completed', 0, '2026-07-19 02:56:33', '2026-09-14 13:37:30', 0),
(70, 'REG/2026/08', 'DMI/INT/200/1/CD(01-08)', 'Nullam vulputate sit amet dolor eget molestie. Aliqu', '2026-07-02', 7, 2, NULL, 'Confidential', 'Normal', 'Maecenas faucibus, velit id mattis ultricies, mi dolor semper ante, in mattis dolor urna ac dui. Sed urna ligula, tempus sit amet arcu in, consequat imperdiet ante. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Proin neque leo, fringilla vel ultricies eget, hendrerit sed elit. In at tempus est. Nunc placerat \r\n\r\n', 'tellus id metus malesuada faucibus. Nulla quis lacus in massa scelerisque ultrices eu eget ante. Morbi fermentum ipsum magna, nec feugiat enim tincidunt eu. Phasellus sed rhoncus odio, quis consequat nisi. Vestibulum non varius mauris. Morbi quis tempor diam. Aliquam hendrerit enim et mauris scelerisque consequat. Suspendisse rhoncus justo sed interdum viverra.', NULL, '1784410384_a.pdf', 4, NULL, 'Forwarded', 0, '2026-07-19 03:03:04', '2026-07-19 03:09:49', 0),
(71, 'REG/2026/09', 'DMI/INT/200/1/CD(01-09)', 'Cras scelerisque quam metus, eget ullamcorper neque fringilla at. Vivamus ultrices arcu co', '2026-07-06', 6, 2, NULL, 'Restricted', 'Normal', 'Nulla euismod justo et faucibus iaculis. Sed consectetur vitae leo ut bibendum. Suspendisse potenti. Sed non aliquet metus. Nulla massa libero, euismod eu sem vitae, vulputate dignissim neque. Interdum et malesuada fames ac ante ipsum primis in faucibus. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. A\r\n\r\n', 'enean fermentum felis id magna rhoncus interdum at et odio. Nulla facilisi. Praesent molestie ante vel diam maximus feugiat. Duis pulvinar et neque ac ultrices. Quisque eu lorem metus. Mauris consequat nisi sed mattis ultrices. Integer sagittis augue sit amet lectus tristique finibus.', NULL, '1784411591_a.pdf', 4, NULL, 'completed', 0, '2026-07-19 03:23:11', '2026-09-14 15:15:00', 0),
(72, 'REG/2026/10', 'DMI/INT/200/1/CD(01-15)', 'Sample Letter 1', '2026-07-21', 1, 2, NULL, 'Restricted', 'Immediately', 'ssssssssssssssssss', 'ssssssssssssss', NULL, '1784412392_a.pdf', 4, 4, 'completed', 0, '2026-07-19 03:36:32', '2026-09-14 15:36:50', 1),
(73, 'REG/2026/11', 'DMI/INT/200/1/CD(01-10)', 'One morning, when Gregor Samsa woke from troubled dreams, he found himself transformed in his bed into a horrible vermin. Update now', '2026-07-21', 3, 2, NULL, 'Confidential', 'Normal', 'He lay on his armour-like back, and if he lifted his head a little he could see his brown belly, slightly domed and divided by arches into stiff sections.\r\n\r\nThe bedding was hardly able to cover it and seemed ready to slide off any moment.\r\n\r\n\"What\'s happened to me? \" he thought. It wasn\'t a dream. His room, a proper human', 'His many legs, pitifully thin compared with the size of the rest of him, waved about helplessly as he looked.', NULL, '1784652931_a.pdf', 4, 4, 'completed', 0, '2026-07-21 22:25:31', '2026-10-06 00:03:26', 1),
(74, 'REG/2026/12', 'DMI/INT/200/1/CD(01-13)', 'Sample Letter 9', '2026-07-26', 1, 1, 1, 'Restricted', 'Normal', 'xxxxxxxxxxxxxxxxxxxxx', 'xxxxxxxxxxxxxx', 9, NULL, 4, NULL, 'completed', 0, '2026-07-26 12:57:07', '2026-09-14 13:59:31', 0),
(75, 'REG/2026/13', 'යුහමු/බුඅම/බුද්ධි/200/1/CD(01-01)', 'සක්විති රණසිංහ සබැඳි විශ්ලේෂණ වාර්තාව', '2026-08-11', 7, 2, NULL, 'Restricted', 'Normal', 'සක්විති රණසිංහ විසින් පසුගිය කාලසීමාව තුළ මුදල් වංචා සිදුකර ඇති බවට තොරතුරු අනාවරණය වී ඇත', '', NULL, NULL, 4, NULL, 'completed', 0, '2026-08-11 17:04:58', '2026-09-19 12:40:01', 1),
(76, 'REG/2026/14', '444444444444', 'XXXXXXXXXXXXXX', '2026-08-11', 1, 1, 1, 'Restricted', 'Normal', '', '', 9, NULL, 4, NULL, 'pending', 0, '2026-08-11 18:00:25', '2026-09-14 13:36:01', 1),
(77, 'REG/2026/15', 'DMI/INT/200/1/CD(01-12)', 'Sample Letter 2', '2026-08-14', 2, 1, 1, 'Confidential', 'Normal', 'XXXXXXXXXXXXXXXXXXXXXXXXX', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXX', 10, NULL, 4, NULL, 'completed', 0, '2026-08-14 20:53:13', '2026-09-27 22:00:18', 1),
(78, 'REG/2026/16', 'SampleRegLetter1', 'dfgdfgdgdgdgdtg', '2026-09-14', 2, 2, NULL, 'Confidential', 'Immediately', 'ghhhjghg', 'hghghgh', NULL, NULL, 4, NULL, 'completed', 0, '2026-09-14 15:22:59', '2026-09-19 12:58:21', 1),
(79, 'REG/2026/17', 'sssss', 'වාර්තාව', '2026-09-19', 3, NULL, NULL, 'Restricted', 'Normal', '', 'MACE ID: Zzsaer1_k', NULL, NULL, 4, 4, 'Forwarded', 0, '2026-09-19 13:22:55', '2026-10-04 22:15:02', 0);

-- --------------------------------------------------------

--
-- Table structure for table `internal_letters_final_disposal`
--

CREATE TABLE `internal_letters_final_disposal` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `forwarded_to` int(11) NOT NULL,
  `forwarded_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `forwarded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `disposed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internal_letters_final_disposal`
--

INSERT INTO `internal_letters_final_disposal` (`id`, `letter_id`, `forwarded_to`, `forwarded_by`, `remarks`, `status`, `forwarded_at`, `disposed_at`) VALUES
(38, 69, 10, 4, '', 'Completed', '2026-07-18 21:34:51', '2026-07-18 21:42:33'),
(39, 74, 9, 8, '', 'Completed', '2026-08-29 20:54:38', '2026-08-30 16:35:40'),
(40, 75, 9, 4, 'file this', 'Completed', '2026-09-14 07:02:02', '2026-09-19 07:10:01'),
(41, 76, 9, 4, '', 'Pending', '2026-09-14 07:02:19', NULL),
(42, 72, 10, 4, '', 'Completed', '2026-09-14 07:19:45', '2026-09-14 10:06:50'),
(43, 66, 11, 4, '', 'Pending', '2026-09-14 08:27:03', NULL),
(44, 67, 9, 4, '', 'Pending', '2026-09-14 08:28:32', NULL),
(45, 68, 10, 4, '', 'Completed', '2026-09-14 08:35:53', '2026-09-14 10:06:44'),
(46, 77, 9, 4, '', 'Completed', '2026-09-14 08:47:21', '2026-09-27 16:30:18'),
(47, 73, 9, 4, '', 'Completed', '2026-09-14 09:23:56', '2026-10-05 18:33:26'),
(48, 71, 9, 4, '', 'Completed', '2026-09-14 09:39:38', '2026-09-14 09:41:02'),
(49, 78, 9, 4, '', 'Completed', '2026-09-14 09:55:25', '2026-09-19 07:28:21');

--
-- Triggers `internal_letters_final_disposal`
--
DELIMITER $$
CREATE TRIGGER `trg_final_disposal_insert` AFTER INSERT ON `internal_letters_final_disposal` FOR EACH ROW BEGIN
    UPDATE internal_letters 
    SET status = 'pending', is_under_final_disposal = 1
    WHERE id = NEW.letter_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_final_disposal_update` AFTER UPDATE ON `internal_letters_final_disposal` FOR EACH ROW BEGIN
    IF NEW.status = 'Completed' THEN
        UPDATE internal_letters 
        SET status = 'completed', is_under_final_disposal = 0
        WHERE id = NEW.letter_id;
    ELSEIF NEW.status = 'pending' THEN
        UPDATE internal_letters 
        SET status = 'pending', is_under_final_disposal = 1
        WHERE id = NEW.letter_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `internal_letters_final_disposal_details`
--

CREATE TABLE `internal_letters_final_disposal_details` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `file_number` varchar(100) DEFAULT NULL,
  `file_location` varchar(255) DEFAULT NULL,
  `disposed_by` int(11) DEFAULT NULL,
  `disposed_at` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','Filed','Completed') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internal_letters_final_disposal_details`
--

INSERT INTO `internal_letters_final_disposal_details` (`id`, `letter_id`, `file_number`, `file_location`, `disposed_by`, `disposed_at`, `status`) VALUES
(13, 69, 'HR-2026-002', 'Folio No: 1', 9, '2026-07-19 03:12:33', 'Completed'),
(14, 74, 'OPS-2026-002', 'Folio No: 1', 4, '2026-08-30 22:05:40', 'Completed'),
(15, 71, 'REG/A/1', 'Folio No: 1', 4, '2026-09-14 15:11:02', 'Completed'),
(16, 68, 'OPS-2026-003', 'Folio No: 1', 9, '2026-09-14 15:36:44', 'Completed'),
(17, 72, 'OPS-2026-003', 'Folio No: 2', 9, '2026-09-14 15:36:50', 'Completed'),
(18, 75, 'AHQ/DMI/INT/200/1/GD/08', 'Folio No: 1', 4, '2026-09-19 12:40:01', 'Completed'),
(19, 78, 'AHQ/DMI/INT/200/1/GD/08', 'Folio No: 2', 4, '2026-09-19 12:58:21', 'Completed'),
(20, 77, 'AHQ/DMI/INT/200/1/GD/08', 'Folio No: 3', 4, '2026-09-27 22:00:18', 'Completed'),
(21, 73, 'REG/A/1', 'Folio No: 2', 4, '2026-10-06 00:03:26', 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `internal_letters_forwarding`
--

CREATE TABLE `internal_letters_forwarding` (
  `id` int(11) NOT NULL,
  `letter_id` int(11) NOT NULL,
  `forwarded_to` int(11) NOT NULL,
  `forwarded_by` int(11) NOT NULL,
  `forwarded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `comments` text DEFAULT NULL,
  `seen` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internal_letters_forwarding`
--

INSERT INTO `internal_letters_forwarding` (`id`, `letter_id`, `forwarded_to`, `forwarded_by`, `forwarded_at`, `comments`, `seen`) VALUES
(72, 65, 8, 4, '2026-07-18 23:49:27', 'L/CPL Madusara RMKD\r\nforward for your info sir;', 0),
(73, 64, 9, 4, '2026-07-18 23:52:47', 'CPL Rajapaksha RPSA\r\nforward for your Info pls', 0),
(74, 65, 9, 4, '2026-07-18 23:53:32', 'CPL Rajapaksha RPSA', 0),
(75, 65, 9, 4, '2026-07-19 01:51:53', 'CPL Rajapaksha RPSA CPL Rajapaksha RPSA', 0),
(76, 65, 9, 4, '2026-07-19 02:04:40', 'CPL Rajapaksha RPSA CPL Rajapaksha RPSA', 0),
(77, 66, 9, 4, '2026-07-19 02:37:31', 'CPL Rajapaksha RPSA CPL Rajapaksha RPSA', 0),
(78, 65, 10, 4, '2026-07-19 02:40:25', 'CPL Wishwantha MH CPL Wishwantha MH', 0),
(79, 69, 9, 4, '2026-07-19 02:56:48', 'CPL Wishwantha MH CPL Rajapaksha RPSA', 0),
(80, 69, 10, 4, '2026-07-19 02:56:48', 'CPL Wishwantha MH CPL Rajapaksha RPSA', 0),
(81, 63, 8, 4, '2026-07-19 03:04:20', 'L/CPL Madusara RMKD L/CPL Madusara RMKD', 0),
(82, 70, 8, 4, '2026-07-19 03:09:49', 'L/CPL Madusara RMKD L/CPL Madusara RMKD', 0),
(83, 71, 10, 4, '2026-07-19 03:25:23', 'CPL Wishwantha MH CPL Wishwantha MH', 0),
(84, 71, 8, 4, '2026-07-21 21:52:16', 'L/CPL Madusara RMKD L/CPL Madusara RMKD', 0),
(85, 68, 10, 4, '2026-07-21 21:52:34', 'CPL Wishwantha MH CPL Wishwantha MH', 0),
(86, 67, 10, 4, '2026-07-21 21:53:27', 'CPL Wishwantha MH CPL Wishwantha MH', 0),
(87, 64, 8, 4, '2026-07-21 21:53:42', 'L/CPL Madusara RMKD L/CPL Madusara RMKD', 0),
(88, 72, 9, 4, '2026-07-21 21:54:32', 'CPL Rajapaksha RPSA CPL Rajapaksha RPSA', 1),
(89, 72, 4, 9, '2026-07-25 14:31:43', 'CPL Perera MLLR', 1),
(90, 72, 4, 9, '2026-07-25 15:04:01', 'CPL Perera MLLR\r\n\r\nForward checking', 1),
(91, 75, 8, 4, '2026-08-11 17:11:50', 'L/CPL Madusara RMKD L/CPL Madusara RMKD', 0),
(93, 77, 8, 4, '2026-08-14 23:28:05', '', 0),
(94, 77, 8, 4, '2026-08-14 23:31:07', '', 0),
(96, 77, 8, 4, '2026-08-14 23:36:34', 'Sir,\r\nfowd for your Info Pls', 0),
(97, 77, 8, 4, '2026-08-14 23:37:03', 'file now', 0),
(98, 77, 9, 4, '2026-08-14 23:38:50', 'file now', 0),
(99, 77, 8, 4, '2026-08-15 00:20:44', 'Sir,\r\nfowd for your Info Pls', 0),
(100, 77, 8, 4, '2026-08-15 00:21:01', 'file now', 0),
(102, 77, 8, 4, '2026-08-15 00:47:49', '', 0),
(103, 77, 8, 4, '2026-08-15 00:47:58', '', 0),
(104, 77, 8, 4, '2026-08-15 00:49:46', '', 0),
(105, 77, 4, 4, '2026-08-15 00:49:46', '', 1),
(106, 76, 8, 4, '2026-08-15 00:50:16', 'Sir,\r\nfowd for your Info Pls', 0),
(108, 77, 8, 4, '2026-08-15 00:53:12', '', 0),
(109, 77, 8, 4, '2026-08-15 00:53:49', '', 0),
(110, 77, 8, 4, '2026-08-15 00:54:11', '', 0),
(111, 77, 8, 4, '2026-08-15 01:23:58', 'Sir,\r\nfowd for your Info Pls', 0),
(112, 77, 13, 4, '2026-08-15 01:23:58', 'Sir,\r\nfowd for your Info Pls', 0),
(113, 75, 8, 4, '2026-08-15 01:26:49', '', 0),
(114, 77, 13, 4, '2026-08-15 01:27:12', 'Sir,\r\nfowd for your Info Pls', 0),
(115, 77, 9, 4, '2026-08-15 01:27:12', 'Sir,\r\nfowd for your Info Pls', 0),
(116, 77, 10, 4, '2026-08-15 01:27:12', 'Sir,\r\nfowd for your Info Pls', 0),
(117, 77, 8, 4, '2026-08-15 01:27:32', '', 0),
(118, 77, 8, 4, '2026-08-15 01:28:07', 'file now', 0),
(119, 77, 8, 4, '2026-08-15 01:28:46', 'file now', 0),
(120, 77, 13, 4, '2026-08-15 01:30:13', 'file now', 0),
(121, 77, 8, 4, '2026-08-15 01:30:28', '', 0),
(122, 76, 4, 4, '2026-08-15 01:30:52', 'Sir,\r\nfowd for your Info Pls', 1),
(123, 72, 8, 4, '2026-08-23 12:20:39', 'file now', 0),
(124, 76, 8, 4, '2026-08-23 12:21:25', '', 0),
(125, 73, 8, 4, '2026-09-14 12:51:09', '', 0),
(126, 73, 8, 4, '2026-09-14 12:51:24', '', 0),
(127, 78, 8, 4, '2026-09-14 15:24:43', 'Sir,\r\nfowd for your Info Pls', 0),
(128, 79, 4, 4, '2026-10-04 22:15:02', 'Sent to NID', 1),
(129, 79, 4, 4, '2026-10-04 22:15:21', 'Sent to NID', 1),
(130, 79, 8, 4, '2026-10-04 22:20:30', 'Sent to NID', 0),
(131, 79, 9, 4, '2026-10-04 22:20:58', 'Sir,\r\nfowd for your Info Pls', 0),
(132, 79, 10, 4, '2026-10-04 22:21:21', 'file now', 0),
(133, 79, 9, 4, '2026-10-04 22:32:38', 'file now', 0),
(134, 79, 4, 4, '2026-10-04 22:32:55', '', 1),
(135, 79, 9, 4, '2026-10-04 22:33:20', '', 0),
(136, 79, 4, 4, '2026-10-04 22:39:12', '', 1),
(137, 79, 9, 4, '2026-10-04 22:39:38', '', 0),
(138, 79, 4, 4, '2026-10-06 00:23:02', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `letter_categories`
--

CREATE TABLE `letter_categories` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `letter_categories`
--

INSERT INTO `letter_categories` (`id`, `category`, `description`, `status`, `created_at`, `is_deleted`) VALUES
(1, 'MISR', 'Military Intelligence Source Report', 'Active', '2026-02-08 15:20:55', 0),
(2, 'INFO', 'Information Letters', 'Active', '2026-02-12 15:49:22', 0),
(3, 'PR', 'Police Reports', 'Active', '2026-02-12 18:45:10', 0);

-- --------------------------------------------------------

--
-- Table structure for table `outward_internal`
--

CREATE TABLE `outward_internal` (
  `id` int(11) NOT NULL,
  `outward_id` int(11) NOT NULL,
  `internal_id` int(11) NOT NULL,
  `source` varchar(255) DEFAULT 'direct'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outward_internal`
--

INSERT INTO `outward_internal` (`id`, `outward_id`, `internal_id`, `source`) VALUES
(74, 35, 74, 'direct'),
(75, 34, 74, 'direct'),
(76, 35, 79, 'direct'),
(77, 35, 78, 'direct'),
(78, 35, 75, 'direct'),
(79, 35, 73, 'Task 18 - Sample Task Letter'),
(80, 35, 72, 'Task 18 - Sample Task Letter'),
(81, 36, 79, 'direct'),
(82, 36, 75, 'direct'),
(83, 36, 73, 'Task 18 - Sample Task Letter'),
(84, 36, 72, 'Task 18 - Sample Task Letter'),
(85, 36, 71, 'Task 17 - AAAAAAAAAAAAAA'),
(86, 36, 67, 'Task 17 - AAAAAAAAAAAAAA');

-- --------------------------------------------------------

--
-- Table structure for table `outward_letters`
--

CREATE TABLE `outward_letters` (
  `id` int(11) NOT NULL,
  `letter_ref` varchar(100) NOT NULL,
  `cdr_no` varchar(50) DEFAULT NULL,
  `letter_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `receiver` varchar(255) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `dispatch_method` enum('By Hand','Email','Post','Courier') DEFAULT 'Email',
  `dispatched_by` int(11) DEFAULT NULL,
  `handed_over_to` varchar(150) DEFAULT NULL,
  `handed_over_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(4) DEFAULT 0,
  `approval_status` varchar(50) DEFAULT 'Pending RA Approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `dispatch_remarks` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outward_letters`
--

INSERT INTO `outward_letters` (`id`, `letter_ref`, `cdr_no`, `letter_date`, `title`, `description`, `receiver`, `comment`, `created_by`, `file_path`, `status`, `dispatch_method`, `dispatched_by`, `handed_over_to`, `handed_over_date`, `created_at`, `updated_at`, `is_deleted`, `approval_status`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `dispatch_remarks`, `submitted_at`) VALUES
(34, 'DMI/INT/200/GD/07(01-02)', 'RPT/2026/01', '2026-08-29', 'Where does it come from?', 'Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.', '5,7', 'The standard chunk of Lorem Ipsum used since 1966 is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from \"de Finibus Bonorum et Malorum\" by Cicero are also reproduced in their exact original form, accompanied by English versions from the 1914 translation by H. Rackham.', 4, 'uploads/outward/out_6a92be82e21e8.pdf', 'Completed', 'Courier', 8, 'S/145236 Cpl Samasaringha SGPK', '2026-09-19 14:34:23', '2026-08-29 11:12:02', '2026-09-19 09:04:23', 0, 'Approved', 9, '2026-08-29 16:50:34', NULL, NULL, '', NULL),
(35, 'DMI/INT/200/GD/07(01-01)', 'RPT/2026/02', '2026-08-29', 'xxxxxxxxxx', 'xxxxxxxxxxxx', '6', 'xxxxxxxxxxxxxx', 4, NULL, 'Completed', 'Post', 8, 'S/145236 Cpl Samasaringha SGPK', '2026-08-30 01:15:52', '2026-08-29 11:51:27', '2026-08-29 19:45:52', 0, 'Approved', 4, '2026-08-30 01:13:00', NULL, NULL, 'Hand Over On 2026.08.29', NULL),
(36, 'report/02', 'RPT/2026/03', '2026-09-19', 'Lahiru Perera', 'ddddddddddddd', '5', 'ddddddddd', 4, 'uploads/outward/out_6aae807a21ce7.pdf', 'Pending', 'By Hand', NULL, NULL, NULL, '2026-09-19 12:30:50', '2026-10-04 09:23:22', 0, 'Approved', 4, '2026-10-04 14:53:22', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `outward_task`
--

CREATE TABLE `outward_task` (
  `id` int(11) NOT NULL,
  `outward_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outward_task`
--

INSERT INTO `outward_task` (`id`, `outward_id`, `task_id`) VALUES
(40, 35, 18),
(41, 36, 18),
(42, 36, 17);

-- --------------------------------------------------------

--
-- Table structure for table `permission_codes`
--

CREATE TABLE `permission_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permission_codes`
--

INSERT INTO `permission_codes` (`id`, `code`, `description`, `category`, `status`, `created_at`, `updated_at`) VALUES
(1, 'REGISTER_INCOMING', 'Authorize the initialization and digital recording of incoming correspondence.| පැමිණෙන ලිපි හුවමාරුව ආරම්භ කිරීම සහ ඩිජිටල් පටිගත කිරීම සඳහා අවසර දෙන්න.', 'Inward Operations', 'Inactive', '2026-02-06 21:12:31', '2026-10-11 14:23:11'),
(2, 'VIEW_CONFIDENTIAL', 'Access restricted letters categorized under confidential security classifications.', 'Preview Operation', 'Active', '2026-02-06 21:12:31', '2026-02-15 22:10:16'),
(3, 'ADMIN_PANEL', 'Full access to the core system configuration and administrative backend.', 'System Management', 'Active', '2026-02-06 21:27:42', '2026-02-15 22:08:19'),
(4, 'MANAGE_USERS', 'Create, modify, and manage departmental user accounts and profiles.', 'System Management', 'Active', '2026-02-06 21:27:42', '2026-02-15 22:08:57'),
(5, 'ADD_DESK', 'ADD_DESK', 'Desk Management Module', 'Active', '2026-02-06 21:27:42', '2026-10-04 16:42:11'),
(6, 'MANAGE_PERMISSIONS', 'Define and assign security clearance levels to system users.', 'System Management', 'Active', '2026-02-06 21:27:42', '2026-02-15 22:09:24'),
(7, 'VIEW_HOMEPAGE', 'Preview the main Homepage', 'Home page Module', 'Active', '2026-02-07 23:29:32', '2026-10-06 00:30:25'),
(8, 'VIEW_USERS', 'View the registry of all active and inactive system personnel.', 'System Management', 'Active', '2026-02-08 09:18:37', '2026-02-15 22:12:37'),
(9, 'VIEW_DESKS', 'View the organizational structure of desks and sections.', 'Desk Management Module', 'Active', '2026-02-08 09:18:54', '2026-10-04 16:42:44'),
(10, 'VIEW_INCOMING_LETTERS', 'Access the digital archive of all letters received by the registry.', 'Preview Operation', 'Active', '2026-02-08 09:27:30', '2026-02-15 22:13:52'),
(11, 'ACCESS_OUTWARD_DASHBOARD', 'ACCESS_OUTWARD_DASHBOARD', 'ACCESS_OUTWARD_DASHBOARD', 'Active', '2026-02-08 09:27:43', '2026-07-11 13:11:30'),
(12, 'ADD_PERMISSION_CODE', 'Create and define new security protocols and permission tags.', 'System Management', 'Active', '2026-02-08 09:50:15', '2026-02-15 22:14:52'),
(15, 'EDIT_PERMISSION_CODE', 'Modify existing security protocols and permission definitions.', 'System Management', 'Active', '2026-02-08 10:02:05', '2026-02-15 22:15:36'),
(16, 'VIEW_INTERNAL_LETTERS', '2. View internal letters', 'Inward Module', 'Active', '2026-02-08 18:14:39', '2026-10-11 13:57:25'),
(17, 'ADD_INTERNAL_LETTER', '1. Add internal letters', 'Inward Module', 'Active', '2026-02-08 19:46:18', '2026-10-11 13:54:47'),
(18, 'MANAGE_REGIMENTS', 'Add or update military units and regiment designations in the system.', 'System Management', 'Active', '2026-02-08 20:23:32', '2026-02-15 22:22:04'),
(19, 'MANAGE_CATEGORIES', 'Organize and manage classification categories for letters.', 'System Management', 'Active', '2026-02-08 20:46:19', '2026-02-15 22:22:13'),
(20, 'VIEW_INFORMERS', 'VIEW_INFORMERS', 'Informers Unity', 'Active', '2026-02-08 23:21:49', '2026-10-03 15:33:21'),
(21, 'EDIT_INTERNAL_LETTER', '4. Edit internal letters', 'Inward Module', 'Active', '2026-02-08 23:43:56', '2026-10-11 14:02:35'),
(22, 'FORWARD_INTERNAL_LETTER', '5. Forward internal letters', 'Inward Module', 'Active', '2026-02-15 19:44:32', '2026-10-11 14:05:18'),
(23, 'VIEW_INTERNAL_LETTERS_FULL_DETAILS', '8. Preview the full details of Internal letters.', 'Inward Module', 'Active', '2026-02-15 23:56:57', '2026-10-11 14:12:19'),
(24, 'PREVIEW_INTERNAL_PDF', '3. Preview Internal PDF', 'Inward Module', 'Active', '2026-02-17 20:19:00', '2026-10-11 14:00:55'),
(25, 'FINAL_DISPOSAL', '6. Internal letters final disposal', 'Inward Module', 'Active', '2026-02-18 21:48:22', '2026-10-11 14:07:43'),
(26, 'ADD_FILE_RECORD', 'ADD_FILE_RECORD', 'Registry', 'Active', '2026-02-19 21:50:30', '2026-02-19 21:50:30'),
(27, 'FORWARD_FINAL_DISPOSAL', 'FORWARD_FINAL_DISPOSAL', 'Registry', 'Inactive', '2026-02-19 23:00:30', '2026-10-11 14:22:24'),
(28, 'DELETE_INTERNAL_LETTER', '7. Delete the internal letters', 'Inward Module', 'Active', '2026-03-01 13:49:09', '2026-10-11 14:09:36'),
(29, 'FINAL_DISPOSAL_FORWARD', 'FINAL_DISPOSAL_FORWARD', 'Registry', 'Inactive', '2026-03-01 15:35:38', '2026-10-11 14:24:18'),
(30, 'VIEW_FINAL_DISPOSAL', 'VIEW_FINAL_DISPOSAL', 'Registry', 'Inactive', '2026-03-02 12:26:40', '2026-10-11 14:24:41'),
(31, 'MANAGE_TASK_LETTERS', 'MANAGE_TASK_LETTERS', 'Task Module', 'Active', '2026-03-07 17:10:20', '2026-10-06 00:39:37'),
(32, 'VIEW_TASK_LETTERS', 'VIEW_TASK_LETTERS', 'Task Module', 'Active', '2026-03-07 22:26:15', '2026-10-06 00:40:04'),
(33, 'ADD_OUTWARD_LETTERS', '1. Add external letters', 'External Module', 'Active', '2026-03-21 16:22:04', '2026-10-11 14:38:34'),
(34, 'VIEW_OUTWARD_LETTERS', '2. View external letters', 'External Module', 'Active', '2026-03-28 11:04:27', '2026-10-11 14:56:28'),
(35, 'ADMIN_ACCESS', 'ADMIN_ACCESS', 'Registry', 'Active', '2026-03-29 00:30:05', '2026-03-29 00:30:05'),
(36, 'ACCESS_HOMEPAGE', 'ACCESS_HOMEPAGE', 'Home page Module', 'Active', '2026-07-11 12:47:17', '2026-10-06 00:31:31'),
(37, 'ACCESS_INTERNAL_DASHBOARD', '1. Access to internal dashboard', 'Dashboard Module', 'Active', '2026-07-11 13:00:36', '2026-10-11 13:52:01'),
(38, 'ACCESS_EXTERNAL_DASHBOARD', '2. Access to external dashboard', 'Dashboard Module', 'Active', '2026-07-11 13:00:46', '2026-10-11 13:52:20'),
(39, 'ACCESS_TASKING_DASHBOARD', '3. Access to tasking dashboard', 'Dashboard Module', 'Active', '2026-07-11 13:00:55', '2026-10-11 13:52:28'),
(40, 'ACCESS_INFORMER_DASHBOARD', '4. Access to informer dashboard', 'Dashboard Module', 'Active', '2026-07-11 14:06:02', '2026-10-11 13:52:38'),
(43, 'REPLY_TASK', 'Allow user to submit replies to task letters', 'Task Management', 'Active', '2026-08-09 22:41:14', '2026-08-09 22:41:14'),
(48, 'DISPATCH_OUTWARD', 'Allow Dispatch Clerk to complete outward dispatch', 'Outward Operations', 'Active', '2026-08-23 16:42:22', '2026-08-23 16:42:22'),
(49, 'RESUBMIT_OUTWARD', '5. Allow user to resubmit rejected outward letters', 'External Module', 'Active', '2026-08-23 16:42:22', '2026-10-11 15:12:36'),
(50, 'APPROVE_OUTWARD', '4. Allow registry assistant to approve or reject external letters', 'External Module', 'Active', '2026-08-23 16:54:50', '2026-10-11 15:10:18'),
(58, 'VIEW_TASK', 'VIEW_TASK', 'Task Letter', 'Active', '2026-08-30 19:40:34', '2026-08-30 19:40:34'),
(59, 'EDIT_TASK', 'EDIT_TASK', 'Task Letter', 'Active', '2026-08-30 19:41:28', '2026-08-30 19:41:28'),
(60, 'VIEW_FILE_RECORDS', 'VIEW_FILE_RECORDS', 'E-Files', 'Active', '2026-09-19 11:12:43', '2026-09-19 11:12:43'),
(61, 'DELETE_FILE_RECORD', 'DELETE_FILE_RECORD', 'E-Files', 'Active', '2026-09-19 11:13:03', '2026-09-19 11:13:03'),
(62, 'EDIT_FILE_RECORD', 'EDIT_FILE_RECORD', 'E-Files', 'Active', '2026-09-19 11:23:01', '2026-09-19 11:23:01'),
(63, 'ADD_INFORMER', 'ADD_INFORMER', 'Informers Unity', 'Active', '2026-10-03 15:34:22', '2026-10-03 15:34:22'),
(64, 'EDIT_INFORMER', 'EDIT_INFORMER', 'Informers Unity', 'Active', '2026-10-03 15:34:38', '2026-10-03 15:34:38'),
(65, 'DELETE_INFORMER', 'DELETE_INFORMER', 'Informers Unity', 'Active', '2026-10-03 15:34:51', '2026-10-03 15:34:51'),
(66, 'VIEW_CATEGORIES', 'View category list', 'LETTER CATEGORIES MANAGEMENT MODULE', 'Active', '2026-10-03 15:49:47', '2026-10-03 15:49:47'),
(67, 'ADD_CATEGORY', 'Create new categories', 'LETTER CATEGORIES MANAGEMENT MODULE', 'Active', '2026-10-03 15:50:25', '2026-10-03 15:50:25'),
(68, 'EDIT_CATEGORY', 'Edit existing categories', 'LETTER CATEGORIES MANAGEMENT MODULE', 'Active', '2026-10-03 15:51:05', '2026-10-03 15:51:05'),
(69, 'DELETE_CATEGORY', 'Delete categories', 'LETTER CATEGORIES MANAGEMENT MODULE', 'Active', '2026-10-03 15:51:32', '2026-10-03 15:51:32'),
(70, 'EDIT_DESK', 'EDIT_DESK', 'Desk Management Module', 'Active', '2026-10-04 16:43:26', '2026-10-04 16:43:26'),
(71, 'DELETE_DESK', 'DELETE_DESK', 'Desk Management Module', 'Active', '2026-10-04 16:43:45', '2026-10-04 16:43:45'),
(72, 'EDIT_OUTWARD_LETTERS', '3. Edit external letters', 'External Module', 'Active', '2026-10-11 14:42:14', '2026-10-11 14:44:08');

-- --------------------------------------------------------

--
-- Table structure for table `physical_file_records`
--

CREATE TABLE `physical_file_records` (
  `id` int(11) NOT NULL,
  `file_reference` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `volume` varchar(50) NOT NULL,
  `rack_no` varchar(50) NOT NULL,
  `row_no` varchar(50) NOT NULL,
  `about_file` text DEFAULT NULL,
  `desk_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `status` enum('ACTIVE','ARCHIVED') DEFAULT 'ACTIVE',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `physical_file_records`
--

INSERT INTO `physical_file_records` (`id`, `file_reference`, `file_name`, `volume`, `rack_no`, `row_no`, `about_file`, `desk_id`, `created_at`, `created_by`, `edited_by`, `edited_at`, `status`, `is_deleted`) VALUES
(1, 'AHQ/DMI/INT/200/1/GD/08', 'Report out - Golf Desk', 'Vol 1', 'Rack 01', 'Row 01', 'this is the Golf Desk\'s file. all External letters filed in it', 9, '2026-02-19 16:23:01', 4, 4, '2026-09-19 06:05:44', 'ACTIVE', 0),
(2, 'FIN-2026-002', '', 'Vol 1', 'Rack 01', 'Row 02', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(3, 'FIN-2026-003', '', 'Vol 2', 'Rack 01', 'Row 01', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(4, 'HR-2026-001', '', 'Vol 1', 'Rack 02', 'Row 01', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(5, 'HR-2026-002', '', 'Vol 2', 'Rack 02', 'Row 02', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(6, 'ADM-2026-001', '', 'Vol 1', 'Rack 03', 'Row 01', NULL, 9, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(7, 'ADM-2026-002', '', 'Vol 1', 'Rack 03', 'Row 02', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(8, 'OPS-2026-001', '', 'Vol 1', 'Rack 04', 'Row 01', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(9, 'OPS-2026-002', '', 'Vol 1', 'Rack 04', 'Row 02', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(10, 'OPS-2026-003', '', 'Vol 2', 'Rack 04', 'Row 01', NULL, NULL, '2026-02-19 16:23:01', 4, NULL, NULL, 'ACTIVE', 0),
(11, 'REG/A/1', '', 'Vol-1', 'Rack 1', 'Row 1', 'This is the internal report of 1 MIC', 9, '2026-03-28 07:00:22', 4, NULL, NULL, 'ACTIVE', 0);

-- --------------------------------------------------------

--
-- Table structure for table `regiments`
--

CREATE TABLE `regiments` (
  `id` int(11) NOT NULL,
  `regiment_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `regiments`
--

INSERT INTO `regiments` (`id`, `regiment_name`, `address`, `status`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, '1 MIC', 'Vavniya', 'Active', 0, '2026-02-08 20:26:16', '2026-02-08 20:26:16'),
(2, '2 MIC', 'Jaffna', 'Active', 0, '2026-02-12 21:23:19', '2026-02-12 21:23:19'),
(3, '3 (V) MIC', 'Girithale', 'Active', 0, '2026-07-11 09:58:17', '2026-07-11 09:58:17'),
(4, '4 MIC', 'Killinochchi', 'Active', 0, '2026-07-11 09:58:49', '2026-07-11 09:58:49'),
(5, '5 MIC', 'Kaluaggala', 'Active', 0, '2026-07-11 09:58:59', '2026-07-11 09:58:59'),
(6, '6 (V) MIC', 'Hambanthota', 'Active', 0, '2026-07-11 09:59:11', '2026-07-11 09:59:11'),
(7, '7 MIC', 'Trincomalee', 'Active', 0, '2026-07-11 09:59:22', '2026-07-11 09:59:22'),
(8, '8 MIC', 'Welimada', 'Active', 0, '2026-07-11 09:59:33', '2026-07-11 09:59:33');

-- --------------------------------------------------------

--
-- Table structure for table `task_internal_links`
--

CREATE TABLE `task_internal_links` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `internal_letter_id` int(11) NOT NULL,
  `linked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_internal_links`
--

INSERT INTO `task_internal_links` (`id`, `task_id`, `internal_letter_id`, `linked_at`) VALUES
(4, 13, 68, '2026-07-26 07:20:06'),
(5, 13, 67, '2026-07-26 07:20:06'),
(6, 14, 66, '2026-08-09 07:31:30'),
(7, 14, 65, '2026-08-09 07:31:30'),
(8, 14, 64, '2026-08-09 07:31:30'),
(9, 14, 63, '2026-08-09 07:31:30'),
(10, 15, 73, '2026-08-09 09:34:02'),
(11, 16, 64, '2026-08-09 09:39:06'),
(12, 17, 73, '2026-08-09 10:57:15'),
(13, 17, 72, '2026-08-09 10:57:16'),
(14, 17, 71, '2026-08-09 10:57:16'),
(15, 17, 67, '2026-08-09 10:57:16'),
(16, 18, 73, '2026-08-11 08:40:17'),
(17, 18, 72, '2026-08-11 08:40:17'),
(18, 19, 76, '2026-10-04 10:12:04');

-- --------------------------------------------------------

--
-- Table structure for table `task_letters`
--

CREATE TABLE `task_letters` (
  `id` int(11) NOT NULL,
  `letter_ref` varchar(100) NOT NULL,
  `tsk_cdr_number` varchar(100) DEFAULT NULL,
  `letter_date` date NOT NULL,
  `request_date` date DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority_status` enum('Low','Medium','High') DEFAULT 'Medium',
  `about_letter` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `status` enum('Pending','Partially Replied','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_letters`
--

INSERT INTO `task_letters` (`id`, `letter_ref`, `tsk_cdr_number`, `letter_date`, `request_date`, `title`, `description`, `priority_status`, `about_letter`, `comments`, `file_path`, `assigned_by`, `status`, `created_at`) VALUES
(13, 'DMI/INT/200/GD/08(01-01)', 'TSK/2026/01', '2026-07-26', '2026-07-31', 'DDDDDDDDDDDDDDDDDDDDDDDDD', 'DDDDDDDDDDDDDDDDDDDDDDDDDD', 'High', 'DDDDDDDDDDDDDDDDDDDDD', 'DDDDDDDDDDDDDDDDDDDDDDDD', NULL, 4, 'Completed', '2026-07-26 07:20:05'),
(14, 'DMI/INT/200/GD/08(01-02)', 'TSK/2026/02', '2026-08-09', '2026-08-14', 'XXXXXXXXXXXXXXXXXXXXX', 'XXXXXXXXXXXXXXXXXXXXXX', 'Medium', 'xxxxxxxxxxxxxxxxxxx', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'uploads/tasks/1786260688_1.pdf', 4, 'Completed', '2026-08-09 07:31:28'),
(15, 'DMI/INT/200/GD/08(01-03)', 'TSK/2026/03', '2026-08-09', '2026-08-11', 'RRRRRRRRRRRRR', 'RRRRRRRRRRRRRRR', 'High', 'RRRRRRRRRRRRRRRRR', 'RRRRRRRRRRRRR', 'uploads/tasks/1786268042_1.pdf', 4, 'Partially Replied', '2026-08-09 09:34:02'),
(16, 'DMI/INT/200/GD/08(01-04)', 'TSK/2026/04', '2026-08-09', '2026-08-07', 'CCCCCCCCCCCCCCCC', 'CCCCCCCCCCCCCCCCCC', 'High', 'CCCCCCCCCCCCCCCCCC', 'CCCCCCCCCCCCCCCCCCCC', NULL, 4, 'Partially Replied', '2026-08-09 09:39:06'),
(17, 'DMI/INT/200/GD/08(01-05)', 'TSK/2026/05', '2026-08-09', '2026-08-13', 'AAAAAAAAAAAAAA', 'AAAAAAAAAAAAAAA', 'High', 'AAAAAAAAAAAAAA', 'AAAAAAAAAAAAAAA', NULL, 9, 'Pending', '2026-08-09 10:57:15'),
(18, 'DMI/INT/200/GD/08(01-11)', 'TSK/2026/06', '2026-08-11', '2026-08-13', 'Sample Task Letter', 'about Task Letters', 'High', 'this is a Sample task letter only', 'get more details about', NULL, 4, 'Pending', '2026-08-11 08:40:16'),
(19, 'DMI/INT/200/GD/08(01-10)', 'TSK/2026/07', '2026-10-04', '2026-10-14', 'ddd', 'fff', 'Medium', '', '', NULL, 4, 'Pending', '2026-10-04 10:12:04');

-- --------------------------------------------------------

--
-- Table structure for table `task_letter_agencies`
--

CREATE TABLE `task_letter_agencies` (
  `id` int(11) NOT NULL,
  `task_letter_id` int(11) NOT NULL,
  `regiment_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_letter_agencies`
--

INSERT INTO `task_letter_agencies` (`id`, `task_letter_id`, `regiment_id`, `assigned_at`) VALUES
(18, 13, 1, '2026-08-09 08:34:58'),
(19, 13, 2, '2026-08-09 08:34:58'),
(20, 13, 3, '2026-08-09 08:34:58'),
(21, 14, 1, '2026-08-09 09:12:09'),
(30, 17, 1, '2026-08-09 10:57:15'),
(31, 17, 2, '2026-08-09 10:57:15'),
(32, 17, 3, '2026-08-09 10:57:15'),
(33, 17, 4, '2026-08-09 10:57:15'),
(34, 17, 5, '2026-08-09 10:57:15'),
(35, 16, 3, '2026-08-09 15:54:50'),
(36, 16, 4, '2026-08-09 15:54:50'),
(37, 16, 5, '2026-08-09 15:54:50'),
(38, 18, 1, '2026-08-11 08:40:16'),
(39, 18, 2, '2026-08-11 08:40:16'),
(40, 18, 3, '2026-08-11 08:40:17'),
(41, 18, 4, '2026-08-11 08:40:17'),
(42, 18, 5, '2026-08-11 08:40:17'),
(43, 15, 1, '2026-08-30 10:30:11'),
(44, 15, 2, '2026-08-30 10:30:11'),
(45, 15, 3, '2026-08-30 10:30:11'),
(46, 15, 4, '2026-08-30 10:30:11'),
(47, 15, 5, '2026-08-30 10:30:11'),
(48, 15, 6, '2026-08-30 10:30:11'),
(49, 15, 7, '2026-08-30 10:30:11'),
(50, 15, 8, '2026-08-30 10:30:11'),
(51, 19, 1, '2026-10-04 10:12:04'),
(52, 19, 2, '2026-10-04 10:12:04'),
(53, 19, 3, '2026-10-04 10:12:04');

-- --------------------------------------------------------

--
-- Table structure for table `task_replies`
--

CREATE TABLE `task_replies` (
  `id` int(11) NOT NULL,
  `task_letter_id` int(11) NOT NULL,
  `regiment_id` int(11) NOT NULL,
  `reply_internal_letter_id` int(11) NOT NULL,
  `status` enum('Accepted','Data NIL') DEFAULT 'Accepted',
  `replied_by` int(11) DEFAULT NULL,
  `replied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_replies`
--

INSERT INTO `task_replies` (`id`, `task_letter_id`, `regiment_id`, `reply_internal_letter_id`, `status`, `replied_by`, `replied_at`) VALUES
(9, 13, 1, 66, 'Accepted', NULL, '2026-07-26 08:33:32'),
(10, 13, 2, 64, 'Data NIL', NULL, '2026-07-28 19:11:22'),
(11, 13, 1, 72, 'Accepted', NULL, '2026-07-28 19:13:34'),
(13, 14, 1, 74, 'Accepted', NULL, '2026-08-09 09:12:45'),
(14, 15, 1, 63, 'Accepted', 4, '2026-08-09 09:35:05'),
(15, 16, 3, 65, 'Accepted', 4, '2026-08-09 09:39:58'),
(16, 13, 3, 73, 'Accepted', 4, '2026-08-09 15:06:50'),
(17, 16, 5, 67, 'Accepted', 4, '2026-08-11 08:54:36'),
(18, 15, 6, 68, 'Accepted', 4, '2026-08-30 14:08:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name_with_initial` varchar(100) NOT NULL,
  `service_number` varchar(30) NOT NULL,
  `rank` varchar(50) NOT NULL,
  `regiment` varchar(100) NOT NULL,
  `work_place` enum('DMI','1 MIC GP','2 MIC GP','3 MIC GP','4 MIC GP','5 MIC GP','6 MIC GP','7 MIC GP','8 MIC GP','OMEGA','SIEERA') NOT NULL,
  `desk_id` int(11) NOT NULL,
  `section` enum('IT Section','Administration Section','Registry Section') NOT NULL DEFAULT 'Administration Section',
  `Appointment` enum('Dispatch Clerk','Subject clerk','Desk Head','Desk Officer','Chief Clerk (CC)','Registry Assistance (RA)','User') NOT NULL,
  `user_category` enum('Officer','ORs') NOT NULL DEFAULT 'ORs',
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name_with_initial`, `service_number`, `rank`, `regiment`, `work_place`, `desk_id`, `section`, `Appointment`, `user_category`, `status`, `is_deleted`, `created_at`, `updated_at`) VALUES
(4, 'Perera', '$2y$10$Y8JnRoo390O.H2ubZuenpORbzrHgvuOBI77xYizl/dw3iSiS7xeBi', 'Perera MLLR', 'S/778110', 'CPL', '5 MIC', 'DMI', 9, 'Registry Section', 'Subject clerk', 'ORs', 'Active', 0, '2026-02-06 22:14:16', '2026-08-23 17:16:28'),
(8, 'Madu', '$2y$10$mFw33MQMcMcXVO0REUH0suPPx7AFiUoC/R1SjhDBy7MluNWcJ5bYK', 'Madusara RMKD', 'S/780051', 'L/CPL', '7 MIC', '1 MIC GP', 9, 'Registry Section', 'Dispatch Clerk', 'ORs', 'Active', 0, '2026-02-08 20:17:18', '2026-08-30 01:14:07'),
(9, 'Raja', '$2y$10$nDJgoqWQapRYjvGGmuPqge873YYMphmslibaxcfkJrFWDSCaBA96i', 'Rajapaksha RPSA', 'S/777332', 'CPL', '2MIC', 'DMI', 10, 'Registry Section', 'Registry Assistance (RA)', 'ORs', 'Active', 0, '2026-02-18 01:18:30', '2026-08-30 01:14:48'),
(10, 'Wish', '$2y$10$g8yZUXHt3bAZ5YFIZm5BKuTovYAePybcbBhtiNv6E6xw2GKt6PYiK', 'Wishwantha MH', 'S/781413', 'CPL', '5 MIC', 'DMI', 3, 'Registry Section', 'Subject clerk', 'ORs', 'Active', 0, '2026-07-11 23:22:24', '2026-08-23 17:09:00'),
(13, 'GD-5', '$2y$10$T7GvoPoaIWimbPE2Z0vaXeX0TIHM0PrQHq3/j6.KJ5iHXsqZ3yTJa', 'Perera MLLR', 'GD-5', 'CPL', '5 MIC', 'DMI', 9, 'Registry Section', 'Dispatch Clerk', 'ORs', 'Inactive', 0, '2026-07-25 16:16:18', '2026-08-30 01:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_code` varchar(100) NOT NULL,
  `is_allowed` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `permission_code`, `is_allowed`, `created_at`, `updated_at`) VALUES
(1795, 9, 'REGISTER_INCOMING', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1796, 9, 'VIEW_CONFIDENTIAL', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1797, 9, 'ADMIN_PANEL', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1798, 9, 'MANAGE_USERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1799, 9, 'ADD_DESK', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1800, 9, 'MANAGE_PERMISSIONS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1801, 9, 'VIEW_HOMEPAGE', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1802, 9, 'VIEW_USERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1803, 9, 'VIEW_DESKS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1804, 9, 'VIEW_INCOMING_LETTERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1805, 9, 'ACCESS_OUTWARD_DASHBOARD', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1806, 9, 'ADD_PERMISSION_CODE', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1807, 9, 'EDIT_PERMISSION_CODE', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1808, 9, 'VIEW_INTERNAL_LETTERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1809, 9, 'ADD_INTERNAL_LETTER', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1810, 9, 'MANAGE_REGIMENTS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1811, 9, 'MANAGE_CATEGORIES', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1812, 9, 'VIEW_INFORMERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1813, 9, 'EDIT_INTERNAL_LETTER', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1814, 9, 'FORWARD_INTERNAL_LETTER', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1815, 9, 'VIEW_INTERNAL_LETTERS_FULL_DETAILS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1816, 9, 'PREVIEW_INTERNAL_PDF', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1817, 9, 'FINAL_DISPOSAL', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1818, 9, 'ADD_FILE_RECORD', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1819, 9, 'FORWARD_FINAL_DISPOSAL', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1820, 9, 'DELETE_INTERNAL_LETTER', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1821, 9, 'FINAL_DISPOSAL_FORWARD', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1822, 9, 'VIEW_FINAL_DISPOSAL', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1823, 9, 'MANAGE_TASK_LETTERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1824, 9, 'VIEW_TASK_LETTERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1825, 9, 'ADD_OUTWARD_LETTERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1826, 9, 'VIEW_OUTWARD_LETTERS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1827, 9, 'ADMIN_ACCESS', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1828, 9, 'ACCESS_HOMEPAGE', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1829, 9, 'ACCESS_INTERNAL_DASHBOARD', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1830, 9, 'ACCESS_EXTERNAL_DASHBOARD', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1831, 9, 'ACCESS_TASKING_DASHBOARD', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1832, 9, 'ACCESS_INFORMER_DASHBOARD', 1, '2026-07-11 21:52:00', '2026-07-11 21:52:00'),
(1838, 10, 'REGISTER_INCOMING', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1839, 10, 'VIEW_CONFIDENTIAL', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1840, 10, 'ADMIN_PANEL', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1841, 10, 'MANAGE_USERS', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1842, 10, 'ADD_DESK', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1843, 10, 'MANAGE_PERMISSIONS', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1844, 10, 'VIEW_HOMEPAGE', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1845, 10, 'VIEW_USERS', 1, '2026-07-19 03:11:31', '2026-07-19 03:11:31'),
(1846, 10, 'VIEW_DESKS', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1847, 10, 'VIEW_INCOMING_LETTERS', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1848, 10, 'ACCESS_OUTWARD_DASHBOARD', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1849, 10, 'ADD_PERMISSION_CODE', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1850, 10, 'EDIT_PERMISSION_CODE', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1851, 10, 'VIEW_INTERNAL_LETTERS', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1852, 10, 'ADD_INTERNAL_LETTER', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1853, 10, 'MANAGE_REGIMENTS', 1, '2026-07-19 03:11:32', '2026-07-19 03:11:32'),
(1854, 10, 'MANAGE_CATEGORIES', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1855, 10, 'VIEW_INFORMERS', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1856, 10, 'EDIT_INTERNAL_LETTER', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1857, 10, 'FORWARD_INTERNAL_LETTER', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1858, 10, 'VIEW_INTERNAL_LETTERS_FULL_DETAILS', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1859, 10, 'PREVIEW_INTERNAL_PDF', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1860, 10, 'FINAL_DISPOSAL', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1861, 10, 'ADD_FILE_RECORD', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1862, 10, 'FORWARD_FINAL_DISPOSAL', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1863, 10, 'DELETE_INTERNAL_LETTER', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1864, 10, 'FINAL_DISPOSAL_FORWARD', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1865, 10, 'VIEW_FINAL_DISPOSAL', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1866, 10, 'MANAGE_TASK_LETTERS', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1867, 10, 'VIEW_TASK_LETTERS', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1868, 10, 'ADD_OUTWARD_LETTERS', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1869, 10, 'VIEW_OUTWARD_LETTERS', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1870, 10, 'ADMIN_ACCESS', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1871, 10, 'ACCESS_HOMEPAGE', 1, '2026-07-19 03:11:33', '2026-07-19 03:11:33'),
(1872, 10, 'ACCESS_INTERNAL_DASHBOARD', 1, '2026-07-19 03:11:34', '2026-07-19 03:11:34'),
(1873, 10, 'ACCESS_EXTERNAL_DASHBOARD', 1, '2026-07-19 03:11:34', '2026-07-19 03:11:34'),
(1874, 10, 'ACCESS_TASKING_DASHBOARD', 1, '2026-07-19 03:11:34', '2026-07-19 03:11:34'),
(1875, 10, 'ACCESS_INFORMER_DASHBOARD', 1, '2026-07-19 03:11:34', '2026-07-19 03:11:34'),
(2246, 9, 'APPROVE_OUTWARD', 1, '2026-08-23 17:14:49', '2026-08-23 17:14:49'),
(2248, 10, 'DISPATCH_OUTWARD', 1, '2026-08-23 17:14:49', '2026-08-23 17:14:49'),
(2378, 8, 'ACCESS_EXTERNAL_DASHBOARD', 1, '2026-08-29 23:51:33', '2026-08-29 23:51:33'),
(2379, 8, 'ACCESS_HOMEPAGE', 1, '2026-08-29 23:51:33', '2026-08-29 23:51:33'),
(2380, 8, 'ACCESS_INFORMER_DASHBOARD', 1, '2026-08-29 23:51:33', '2026-08-29 23:51:33'),
(2381, 8, 'ACCESS_INTERNAL_DASHBOARD', 1, '2026-08-29 23:51:33', '2026-08-29 23:51:33'),
(2382, 8, 'ACCESS_OUTWARD_DASHBOARD', 1, '2026-08-29 23:51:33', '2026-08-29 23:51:33'),
(2383, 8, 'ACCESS_TASKING_DASHBOARD', 1, '2026-08-29 23:51:33', '2026-08-29 23:51:33'),
(2384, 8, 'EDIT_INTERNAL_LETTER', 1, '2026-08-29 23:51:33', '2026-08-29 23:51:33'),
(2385, 8, 'FORWARD_INTERNAL_LETTER', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2386, 8, 'VIEW_INFORMERS', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2387, 8, 'REGISTER_INCOMING', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2388, 8, 'ADD_INTERNAL_LETTER', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2389, 8, 'VIEW_INTERNAL_LETTERS_FULL_DETAILS', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2390, 8, 'DISPATCH_OUTWARD', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2391, 8, 'APPROVE_OUTWARD', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2392, 8, 'RESUBMIT_OUTWARD', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2394, 8, 'VIEW_CONFIDENTIAL', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2395, 8, 'VIEW_INCOMING_LETTERS', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2396, 8, 'VIEW_HOMEPAGE', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2397, 8, 'VIEW_INTERNAL_LETTERS', 1, '2026-08-29 23:51:34', '2026-08-29 23:51:34'),
(2398, 8, 'ADD_FILE_RECORD', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2399, 8, 'ADMIN_ACCESS', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2400, 8, 'DELETE_INTERNAL_LETTER', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2401, 8, 'FINAL_DISPOSAL', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2402, 8, 'FINAL_DISPOSAL_FORWARD', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2403, 8, 'FORWARD_FINAL_DISPOSAL', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2404, 8, 'ADD_OUTWARD_LETTERS', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2405, 8, 'PREVIEW_INTERNAL_PDF', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2406, 8, 'VIEW_FINAL_DISPOSAL', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2407, 8, 'VIEW_OUTWARD_LETTERS', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2408, 8, 'MANAGE_REGIMENTS', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2409, 8, 'ADD_DESK', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2410, 8, 'ADD_PERMISSION_CODE', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2411, 8, 'MANAGE_USERS', 1, '2026-08-29 23:51:35', '2026-08-29 23:51:35'),
(2412, 8, 'MANAGE_PERMISSIONS', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2413, 8, 'ADMIN_PANEL', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2414, 8, 'EDIT_PERMISSION_CODE', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2415, 8, 'MANAGE_CATEGORIES', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2416, 8, 'VIEW_DESKS', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2417, 8, 'VIEW_USERS', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2418, 8, 'MANAGE_TASK_LETTERS', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2419, 8, 'VIEW_TASK_LETTERS', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2420, 8, 'REPLY_TASK', 1, '2026-08-29 23:51:36', '2026-08-29 23:51:36'),
(2695, 13, 'ACCESS_EXTERNAL_DASHBOARD', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2696, 13, 'ACCESS_HOMEPAGE', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2697, 13, 'ACCESS_INFORMER_DASHBOARD', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2698, 13, 'ACCESS_INTERNAL_DASHBOARD', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2699, 13, 'ACCESS_OUTWARD_DASHBOARD', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2700, 13, 'ACCESS_TASKING_DASHBOARD', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2701, 13, 'EDIT_INTERNAL_LETTER', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2702, 13, 'FORWARD_INTERNAL_LETTER', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2703, 13, 'ADD_INFORMER', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2704, 13, 'DELETE_INFORMER', 1, '2026-10-03 15:36:49', '2026-10-03 15:36:49'),
(2705, 13, 'EDIT_INFORMER', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2706, 13, 'VIEW_INFORMERS', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2707, 13, 'REGISTER_INCOMING', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2708, 13, 'ADD_INTERNAL_LETTER', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2709, 13, 'VIEW_INTERNAL_LETTERS_FULL_DETAILS', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2710, 13, 'DISPATCH_OUTWARD', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2711, 13, 'APPROVE_OUTWARD', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2712, 13, 'RESUBMIT_OUTWARD', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2714, 13, 'VIEW_CONFIDENTIAL', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2715, 13, 'VIEW_INCOMING_LETTERS', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2716, 13, 'VIEW_HOMEPAGE', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2717, 13, 'VIEW_INTERNAL_LETTERS', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2718, 13, 'ADD_FILE_RECORD', 1, '2026-10-03 15:36:50', '2026-10-03 15:36:50'),
(2719, 13, 'ADMIN_ACCESS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2720, 13, 'DELETE_INTERNAL_LETTER', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2721, 13, 'FINAL_DISPOSAL', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2722, 13, 'FINAL_DISPOSAL_FORWARD', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2723, 13, 'FORWARD_FINAL_DISPOSAL', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2724, 13, 'ADD_OUTWARD_LETTERS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2725, 13, 'PREVIEW_INTERNAL_PDF', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2726, 13, 'VIEW_FINAL_DISPOSAL', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2727, 13, 'VIEW_OUTWARD_LETTERS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2728, 13, 'MANAGE_REGIMENTS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2729, 13, 'ADD_DESK', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2730, 13, 'ADD_PERMISSION_CODE', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2731, 13, 'MANAGE_USERS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2732, 13, 'MANAGE_PERMISSIONS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2733, 13, 'ADMIN_PANEL', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2734, 13, 'EDIT_PERMISSION_CODE', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2735, 13, 'MANAGE_CATEGORIES', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2736, 13, 'VIEW_DESKS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2737, 13, 'VIEW_USERS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2738, 13, 'MANAGE_TASK_LETTERS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2739, 13, 'VIEW_TASK_LETTERS', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2740, 13, 'REPLY_TASK', 1, '2026-10-03 15:36:51', '2026-10-03 15:36:51'),
(2958, 4, 'ACCESS_OUTWARD_DASHBOARD', 1, '2026-10-11 14:52:12', '2026-10-11 14:52:12'),
(2959, 4, 'ACCESS_INTERNAL_DASHBOARD', 1, '2026-10-11 14:52:12', '2026-10-11 14:52:12'),
(2960, 4, 'ACCESS_EXTERNAL_DASHBOARD', 1, '2026-10-11 14:52:12', '2026-10-11 14:52:12'),
(2961, 4, 'ACCESS_TASKING_DASHBOARD', 1, '2026-10-11 14:52:12', '2026-10-11 14:52:12'),
(2962, 4, 'ACCESS_INFORMER_DASHBOARD', 1, '2026-10-11 14:52:12', '2026-10-11 14:52:12'),
(2963, 4, 'ADD_DESK', 1, '2026-10-11 14:52:12', '2026-10-11 14:52:12'),
(2964, 4, 'DELETE_DESK', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2965, 4, 'EDIT_DESK', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2966, 4, 'VIEW_DESKS', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2967, 4, 'DELETE_FILE_RECORD', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2968, 4, 'EDIT_FILE_RECORD', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2969, 4, 'VIEW_FILE_RECORDS', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2970, 4, 'ADD_OUTWARD_LETTERS', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2972, 4, 'EDIT_OUTWARD_LETTERS', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2973, 4, 'ACCESS_HOMEPAGE', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2974, 4, 'VIEW_HOMEPAGE', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2975, 4, 'ADD_INFORMER', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2976, 4, 'DELETE_INFORMER', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2977, 4, 'EDIT_INFORMER', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2978, 4, 'VIEW_INFORMERS', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2979, 4, 'ADD_INTERNAL_LETTER', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2980, 4, 'VIEW_INTERNAL_LETTERS', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2981, 4, 'PREVIEW_INTERNAL_PDF', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2982, 4, 'EDIT_INTERNAL_LETTER', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2983, 4, 'FORWARD_INTERNAL_LETTER', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2984, 4, 'FINAL_DISPOSAL', 1, '2026-10-11 14:52:13', '2026-10-11 14:52:13'),
(2985, 4, 'DELETE_INTERNAL_LETTER', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2986, 4, 'VIEW_INTERNAL_LETTERS_FULL_DETAILS', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2987, 4, 'ADD_CATEGORY', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2988, 4, 'DELETE_CATEGORY', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2989, 4, 'EDIT_CATEGORY', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2990, 4, 'VIEW_CATEGORIES', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2991, 4, 'VIEW_OUTWARD_LETTERS', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2992, 4, 'DISPATCH_OUTWARD', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2993, 4, 'APPROVE_OUTWARD', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2994, 4, 'RESUBMIT_OUTWARD', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2995, 4, 'VIEW_CONFIDENTIAL', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2996, 4, 'VIEW_INCOMING_LETTERS', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2997, 4, 'ADD_FILE_RECORD', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2998, 4, 'ADMIN_ACCESS', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(2999, 4, 'MANAGE_REGIMENTS', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(3000, 4, 'ADD_PERMISSION_CODE', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(3001, 4, 'MANAGE_USERS', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(3002, 4, 'MANAGE_PERMISSIONS', 1, '2026-10-11 14:52:14', '2026-10-11 14:52:14'),
(3003, 4, 'ADMIN_PANEL', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3004, 4, 'EDIT_PERMISSION_CODE', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3005, 4, 'MANAGE_CATEGORIES', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3006, 4, 'VIEW_USERS', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3007, 4, 'EDIT_TASK', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3008, 4, 'VIEW_TASK', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3009, 4, 'REPLY_TASK', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3010, 4, 'MANAGE_TASK_LETTERS', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15'),
(3011, 4, 'VIEW_TASK_LETTERS', 1, '2026-10-11 14:52:15', '2026-10-11 14:52:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `desks`
--
ALTER TABLE `desks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `desk_name` (`desk_name`);

--
-- Indexes for table `external_branches`
--
ALTER TABLE `external_branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `file_issuances`
--
ALTER TABLE `file_issuances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `issued_to` (`issued_to`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `informers`
--
ALTER TABLE `informers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `informer_code` (`informer_code`);

--
-- Indexes for table `internal_letters`
--
ALTER TABLE `internal_letters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cdr_number` (`cdr_number`),
  ADD KEY `idx_regiment` (`regiment_id`),
  ADD KEY `idx_informer` (`Informer_id`),
  ADD KEY `idx_registered_by` (`registered_by`),
  ADD KEY `idx_letter_date` (`letter_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `fk_internal_letter_category` (`category_id`),
  ADD KEY `fk_letters_updated_by` (`updated_by`);

--
-- Indexes for table `internal_letters_final_disposal`
--
ALTER TABLE `internal_letters_final_disposal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`),
  ADD KEY `forwarded_by` (`forwarded_by`),
  ADD KEY `fk_final_disposal_target_desk` (`forwarded_to`);

--
-- Indexes for table `internal_letters_final_disposal_details`
--
ALTER TABLE `internal_letters_final_disposal_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`),
  ADD KEY `disposed_by` (`disposed_by`);

--
-- Indexes for table `internal_letters_forwarding`
--
ALTER TABLE `internal_letters_forwarding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_id` (`letter_id`),
  ADD KEY `forwarded_to` (`forwarded_to`),
  ADD KEY `forwarded_by` (`forwarded_by`);

--
-- Indexes for table `letter_categories`
--
ALTER TABLE `letter_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category` (`category`),
  ADD KEY `idx_lc_is_deleted` (`is_deleted`);

--
-- Indexes for table `outward_internal`
--
ALTER TABLE `outward_internal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outward_id` (`outward_id`),
  ADD KEY `internal_id` (`internal_id`);

--
-- Indexes for table `outward_letters`
--
ALTER TABLE `outward_letters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `letter_ref` (`letter_ref`);

--
-- Indexes for table `outward_task`
--
ALTER TABLE `outward_task`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outward_id` (`outward_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `permission_codes`
--
ALTER TABLE `permission_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `physical_file_records`
--
ALTER TABLE `physical_file_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_reference` (`file_reference`),
  ADD KEY `rack_no` (`rack_no`),
  ADD KEY `row_no` (`row_no`),
  ADD KEY `fk_desk` (`desk_id`);

--
-- Indexes for table `regiments`
--
ALTER TABLE `regiments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `regiment_name` (`regiment_name`);

--
-- Indexes for table `task_internal_links`
--
ALTER TABLE `task_internal_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `task_letters`
--
ALTER TABLE `task_letters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tsk_cdr_number` (`tsk_cdr_number`),
  ADD KEY `fk_task_assigned_by` (`assigned_by`);

--
-- Indexes for table `task_letter_agencies`
--
ALTER TABLE `task_letter_agencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_task_regiment` (`task_letter_id`,`regiment_id`),
  ADD KEY `fk_tla_task` (`task_letter_id`),
  ADD KEY `fk_tla_regiment` (`regiment_id`);

--
-- Indexes for table `task_replies`
--
ALTER TABLE `task_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_letter_id` (`task_letter_id`),
  ADD KEY `regiment_id` (`regiment_id`),
  ADD KEY `reply_internal_letter_id` (`reply_internal_letter_id`),
  ADD KEY `replied_by` (`replied_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `service_number` (`service_number`),
  ADD KEY `fk_users_desk` (`desk_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_permissions_user` (`user_id`),
  ADD KEY `fk_user_permissions_code` (`permission_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `desks`
--
ALTER TABLE `desks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `external_branches`
--
ALTER TABLE `external_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `file_issuances`
--
ALTER TABLE `file_issuances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `informers`
--
ALTER TABLE `informers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `internal_letters`
--
ALTER TABLE `internal_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `internal_letters_final_disposal`
--
ALTER TABLE `internal_letters_final_disposal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `internal_letters_final_disposal_details`
--
ALTER TABLE `internal_letters_final_disposal_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `internal_letters_forwarding`
--
ALTER TABLE `internal_letters_forwarding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `letter_categories`
--
ALTER TABLE `letter_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `outward_internal`
--
ALTER TABLE `outward_internal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `outward_letters`
--
ALTER TABLE `outward_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `outward_task`
--
ALTER TABLE `outward_task`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `permission_codes`
--
ALTER TABLE `permission_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `physical_file_records`
--
ALTER TABLE `physical_file_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `regiments`
--
ALTER TABLE `regiments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `task_internal_links`
--
ALTER TABLE `task_internal_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `task_letters`
--
ALTER TABLE `task_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `task_letter_agencies`
--
ALTER TABLE `task_letter_agencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `task_replies`
--
ALTER TABLE `task_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3012;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `file_issuances`
--
ALTER TABLE `file_issuances`
  ADD CONSTRAINT `file_issuances_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `physical_file_records` (`id`),
  ADD CONSTRAINT `file_issuances_ibfk_2` FOREIGN KEY (`issued_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `file_issuances_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `internal_letters`
--
ALTER TABLE `internal_letters`
  ADD CONSTRAINT `fk_internal_letter_category` FOREIGN KEY (`category_id`) REFERENCES `letter_categories` (`id`),
  ADD CONSTRAINT `fk_internal_letters_informer` FOREIGN KEY (`Informer_id`) REFERENCES `informers` (`id`),
  ADD CONSTRAINT `fk_internal_letters_regiment` FOREIGN KEY (`regiment_id`) REFERENCES `regiments` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_letters_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_letters_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `internal_letters_final_disposal`
--
ALTER TABLE `internal_letters_final_disposal`
  ADD CONSTRAINT `fk_final_disposal_target_desk` FOREIGN KEY (`forwarded_to`) REFERENCES `desks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `internal_letters_final_disposal_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `internal_letters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `internal_letters_final_disposal_ibfk_3` FOREIGN KEY (`forwarded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internal_letters_final_disposal_details`
--
ALTER TABLE `internal_letters_final_disposal_details`
  ADD CONSTRAINT `fk_ilfd_details_letter` FOREIGN KEY (`letter_id`) REFERENCES `internal_letters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ilfd_details_user` FOREIGN KEY (`disposed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `internal_letters_final_disposal_details_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `internal_letters` (`id`),
  ADD CONSTRAINT `internal_letters_final_disposal_details_ibfk_2` FOREIGN KEY (`disposed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `internal_letters_forwarding`
--
ALTER TABLE `internal_letters_forwarding`
  ADD CONSTRAINT `internal_letters_forwarding_ibfk_1` FOREIGN KEY (`letter_id`) REFERENCES `internal_letters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `internal_letters_forwarding_ibfk_2` FOREIGN KEY (`forwarded_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `internal_letters_forwarding_ibfk_3` FOREIGN KEY (`forwarded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `outward_internal`
--
ALTER TABLE `outward_internal`
  ADD CONSTRAINT `outward_internal_ibfk_1` FOREIGN KEY (`outward_id`) REFERENCES `outward_letters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outward_internal_ibfk_2` FOREIGN KEY (`internal_id`) REFERENCES `internal_letters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `outward_task`
--
ALTER TABLE `outward_task`
  ADD CONSTRAINT `outward_task_ibfk_1` FOREIGN KEY (`outward_id`) REFERENCES `outward_letters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `outward_task_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `task_letters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `physical_file_records`
--
ALTER TABLE `physical_file_records`
  ADD CONSTRAINT `fk_desk` FOREIGN KEY (`desk_id`) REFERENCES `desks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `task_internal_links`
--
ALTER TABLE `task_internal_links`
  ADD CONSTRAINT `task_internal_links_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `task_letters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_letters`
--
ALTER TABLE `task_letters`
  ADD CONSTRAINT `fk_task_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `task_letter_agencies`
--
ALTER TABLE `task_letter_agencies`
  ADD CONSTRAINT `fk_tla_regiment` FOREIGN KEY (`regiment_id`) REFERENCES `regiments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tla_task` FOREIGN KEY (`task_letter_id`) REFERENCES `task_letters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `task_replies`
--
ALTER TABLE `task_replies`
  ADD CONSTRAINT `task_replies_ibfk_1` FOREIGN KEY (`task_letter_id`) REFERENCES `task_letters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_replies_ibfk_2` FOREIGN KEY (`regiment_id`) REFERENCES `regiments` (`id`),
  ADD CONSTRAINT `task_replies_ibfk_3` FOREIGN KEY (`reply_internal_letter_id`) REFERENCES `internal_letters` (`id`),
  ADD CONSTRAINT `task_replies_ibfk_4` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_desk` FOREIGN KEY (`desk_id`) REFERENCES `desks` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_user_permissions_code` FOREIGN KEY (`permission_code`) REFERENCES `permission_codes` (`code`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
