import { IsEmail, IsOptional, IsString, MinLength, Matches } from 'class-validator';

export class CreateUserDto {
  @IsString()
  fullname!: string; // ไทย: ชื่อเต็ม

  @IsString()
  @MinLength(3)
  username!: string;

  @IsEmail()
  email!: string;

  @IsOptional()
  @IsString()
  address?: string;

  @IsOptional()
  @Matches(/^[0-9+\-()\s]{6,20}$/)
  phone?: string;

  @IsString()
  @MinLength(8) // ไทย: เพิ่มความยาวขั้นต่ำเป็น 8
  password!: string;
}
