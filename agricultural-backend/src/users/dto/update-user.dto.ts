import { PartialType } from '@nestjs/mapped-types';
import { CreateUserDto } from './create-user.dto';
import { IsOptional, IsString, MinLength, Matches, IsEmail } from 'class-validator';

export class UpdateUserDto extends PartialType(CreateUserDto) {
  @IsOptional() @IsString() fullname?: string;
  @IsOptional() @IsString() @MinLength(3) username?: string;
  @IsOptional() @IsEmail() email?: string;
  @IsOptional() @IsString() address?: string;
  @IsOptional() @Matches(/^[0-9+\-()\s]{6,20}$/) phone?: string;

  // ไทย: อนุญาตเปลี่ยนรหัสผ่าน
  @IsOptional() @IsString() @MinLength(8) password?: string;
}
