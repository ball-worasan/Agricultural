import { IsString, MinLength, Matches } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class ChangePasswordDto {
  @ApiProperty({ minLength: 8, example: 'NewP@ss_123' })
  @IsString()
  @MinLength(8)
  password!: string;

  @ApiProperty({ minLength: 8, example: 'NewP@ss_123' })
  @IsString()
  @MinLength(8)
  confirm!: string;
}
