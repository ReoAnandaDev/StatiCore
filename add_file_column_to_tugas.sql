-- Add file_path column to tugas table
-- This SQL script adds a file_path column to store uploaded task files

ALTER TABLE tugas ADD COLUMN file_path VARCHAR(255) NULL AFTER deskripsi;

-- Add comment to document the column
ALTER TABLE tugas MODIFY COLUMN file_path VARCHAR(255) NULL COMMENT 'Path to uploaded task file'; 