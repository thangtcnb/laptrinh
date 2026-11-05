-- Migration: Xóa bỏ dữ liệu mẫu tự động trong bảng system_changelogs để Super Admin tự nhập thủ công
DELETE FROM `system_changelogs` WHERE `created_by` IS NULL AND `version` IN ('v2.5', 'v2.4', 'v2.3');
